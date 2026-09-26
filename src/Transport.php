<?php

namespace JplCodes\Bloomerang;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JplCodes\Bloomerang\Auth\Authenticator;
use JplCodes\Bloomerang\Exceptions\AuthenticationFailed;
use JplCodes\Bloomerang\Exceptions\BloomerangException;
use JplCodes\Bloomerang\Exceptions\ConnectionFailed;
use JplCodes\Bloomerang\Exceptions\NotFound;
use JplCodes\Bloomerang\Exceptions\RateLimited;
use JplCodes\Bloomerang\Exceptions\RequestRejected;
use JplCodes\Bloomerang\Exceptions\ServerError;
use JplCodes\Bloomerang\Exceptions\UnexpectedResponse;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The single place HTTP calls to Bloomerang are made.
 *
 * @internal
 */
final class Transport
{
    /**
     * HTTP methods that Bloomerang would not treat as safe to repeat.
     */
    private const NON_IDEMPOTENT_METHODS = ['POST', 'PATCH'];

    /**
     * @param  array<string, mixed>  $requestModeConfig
     * @param  array<string, mixed>  $jobModeConfig
     */
    public function __construct(
        private readonly Factory $http,
        private readonly Authenticator $authenticator,
        private readonly string $baseUrl,
        private readonly array $requestModeConfig,
        private readonly array $jobModeConfig,
        private readonly LoggerInterface $logger,
        private readonly bool $loggingEnabled,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     *
     * @throws BloomerangException
     */
    public function send(CallMode $mode, string $method, string $path, array $query = [], ?array $body = null): mixed
    {
        $this->guardPath($path);

        $method = strtoupper($method);
        $normalizedPath = ltrim($path, '/');
        $url = rtrim($this->baseUrl, '/').'/'.$normalizedPath;

        $modeConfig = $mode === CallMode::Job ? $this->jobModeConfig : $this->requestModeConfig;
        $maxAttempts = $mode === CallMode::Job ? 1 + (int) ($modeConfig['retries'] ?? 0) : 1;
        $idempotent = ! in_array($method, self::NON_IDEMPOTENT_METHODS, true);

        $correlationId = (string) Str::uuid();
        $attempt = 0;

        while (true) {
            $attempt++;
            $startedAt = microtime(true);

            try {
                $response = $this->attempt($mode, $modeConfig, $method, $url, $query, $body);
            } catch (ConnectionException) {
                $durationMs = $this->durationInMs($startedAt);
                $this->log(false, $correlationId, $mode, $method, $normalizedPath, $query, null, $attempt, $durationMs);

                if ($idempotent && $attempt < $maxAttempts) {
                    $this->wait($attempt, $modeConfig, null);

                    continue;
                }

                throw new ConnectionFailed(
                    "Could not reach Bloomerang for {$method} {$normalizedPath} [correlation id {$correlationId}]",
                    $correlationId,
                );
            }

            $durationMs = $this->durationInMs($startedAt);
            $status = $response->status();

            if ($response->successful()) {
                $this->log(true, $correlationId, $mode, $method, $normalizedPath, $query, $status, $attempt, $durationMs);

                return $this->decode($response, $method, $normalizedPath, $correlationId);
            }

            $this->log(false, $correlationId, $mode, $method, $normalizedPath, $query, $status, $attempt, $durationMs);

            $retryable = $idempotent ? ($status === 429 || $status >= 500) : $status === 429;

            if ($retryable && $attempt < $maxAttempts) {
                $retryAfter = $status === 429 ? $this->retryAfterHeader($response) : null;
                $this->wait($attempt, $modeConfig, $retryAfter);

                continue;
            }

            throw $this->mapError($status, $method, $normalizedPath, $correlationId, $response);
        }
    }

    /**
     * @param  array<string, mixed>  $modeConfig
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     *
     * @throws ConnectionException
     */
    private function attempt(CallMode $mode, array $modeConfig, string $method, string $url, array $query, ?array $body): Response
    {
        $request = $this->http->acceptJson()
            ->timeout((int) ($modeConfig['timeout'] ?? 30))
            ->connectTimeout((int) ($modeConfig['connect_timeout'] ?? 5));

        $request = $this->authenticator->authenticate($request);

        if ($body !== null) {
            $request = $request->withBody(json_encode($body), 'application/json');
        }

        $options = $query !== [] ? ['query' => $query] : [];

        return $request->send($method, $url, $options);
    }

    private function guardPath(string $path): void
    {
        if (str_contains($path, '://') || str_starts_with($path, '//')) {
            throw new InvalidArgumentException("The Bloomerang path [{$path}] must not include a host.");
        }
    }

    /**
     * @throws UnexpectedResponse
     */
    private function decode(Response $response, string $method, string $path, string $correlationId): mixed
    {
        $body = $response->body();

        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedResponse(
                "Bloomerang sent a response that could not be read for {$method} {$path} [correlation id {$correlationId}]",
                $correlationId,
                $response->status(),
            );
        }

        return $decoded;
    }

    private function mapError(int $status, string $method, string $path, string $correlationId, Response $response): BloomerangException
    {
        $message = "Bloomerang returned {$status} for {$method} {$path} [correlation id {$correlationId}]";

        return match (true) {
            $status === 401 || $status === 403 => new AuthenticationFailed($message, $correlationId, $status),
            $status === 404 => new NotFound($message, $correlationId, $status),
            $status === 429 => new RateLimited($message, $correlationId, $status, $this->parseRetryAfter($this->retryAfterHeader($response))),
            $status >= 500 => new ServerError($message, $correlationId, $status),
            default => new RequestRejected($message, $correlationId, $status),
        };
    }

    /**
     * @param  array<string, mixed>  $modeConfig
     */
    private function wait(int $attempt, array $modeConfig, ?string $retryAfter): void
    {
        if ($retryAfter !== null) {
            $seconds = $this->parseRetryAfter($retryAfter) ?? 0;
            $maxRetryAfter = (int) ($modeConfig['max_retry_after'] ?? 60);
            Sleep::for(min($seconds, $maxRetryAfter))->seconds();

            return;
        }

        /** @var list<int> $backoff */
        $backoff = $modeConfig['backoff_ms'] ?? [1000];
        $index = min($attempt - 1, count($backoff) - 1);
        $milliseconds = $backoff[$index];

        $jitterMs = (int) ($modeConfig['jitter_ms'] ?? 0);
        if ($jitterMs > 0) {
            $milliseconds += random_int(0, $jitterMs);
        }

        Sleep::for($milliseconds)->milliseconds();
    }

    private function retryAfterHeader(Response $response): ?string
    {
        $header = $response->header('Retry-After');

        return $header === '' ? null : $header;
    }

    private function parseRetryAfter(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (ctype_digit($value)) {
            return (int) $value;
        }

        try {
            $seconds = CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($value), false);
        } catch (Throwable) {
            return null;
        }

        return max(0, $seconds);
    }

    private function durationInMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function log(
        bool $successful,
        string $correlationId,
        CallMode $mode,
        string $method,
        string $path,
        array $query,
        ?int $status,
        int $attempt,
        int $durationMs,
    ): void {
        if (! $this->loggingEnabled) {
            return;
        }

        $context = [
            'correlation_id' => $correlationId,
            'mode' => $mode->value,
            'method' => $method,
            'path' => $path,
            'query_keys' => array_keys($query),
            'status' => $status,
            'attempt' => $attempt,
            'duration_ms' => $durationMs,
        ];

        if ($successful) {
            $this->logger->debug('Bloomerang request completed', $context);
        } else {
            $this->logger->warning('Bloomerang request failed', $context);
        }
    }
}
