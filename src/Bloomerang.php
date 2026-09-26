<?php

namespace JplCodes\Bloomerang;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\LazyCollection;
use JplCodes\Bloomerang\Auth\Authenticator;
use JplCodes\Bloomerang\Auth\BearerToken;
use JplCodes\Bloomerang\Data\User;
use JplCodes\Bloomerang\Pagination\Paginator;
use JplCodes\Bloomerang\Resources\Constituents;
use Psr\Log\LoggerInterface;

/**
 * A client for Bloomerang's REST API v2.
 */
final class Bloomerang
{
    public const string DEFAULT_BASE_URL = 'https://api.bloomerang.co/v2';

    /**
     * @param  array<string, mixed>  $requestModeConfig
     * @param  array<string, mixed>  $jobModeConfig
     */
    public function __construct(
        private readonly Factory $http,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly array $requestModeConfig,
        private readonly array $jobModeConfig,
        private readonly bool $loggingEnabled,
        private readonly Authenticator $authenticator,
        private readonly CallMode $mode = CallMode::Request,
    ) {}

    public function constituents(): Constituents
    {
        return new Constituents($this->transport(), $this->mode);
    }

    public function currentUser(): User
    {
        $data = $this->transport()->send($this->mode, 'GET', 'user/current');

        return User::fromArray($data);
    }

    /**
     * Call any Bloomerang endpoint directly, for anything the package does not wrap.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): mixed
    {
        return $this->transport()->send($this->mode, $method, $path, $query, $body);
    }

    /**
     * Walk any Bloomerang list endpoint, yielding each raw result.
     *
     * @param  array<string, mixed>  $query
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function paginate(string $path, array $query = []): LazyCollection
    {
        return (new Paginator($this->transport(), $this->mode))->walk($path, $query);
    }

    public function inJobMode(): static
    {
        return $this->withMode(CallMode::Job);
    }

    public function inRequestMode(): static
    {
        return $this->withMode(CallMode::Request);
    }

    public function mode(): CallMode
    {
        return $this->mode;
    }

    public function withToken(string $token): static
    {
        return new self(
            $this->http,
            $this->logger,
            $this->baseUrl,
            $this->requestModeConfig,
            $this->jobModeConfig,
            $this->loggingEnabled,
            new BearerToken($token),
            $this->mode,
        );
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    private function withMode(CallMode $mode): static
    {
        return new self(
            $this->http,
            $this->logger,
            $this->baseUrl,
            $this->requestModeConfig,
            $this->jobModeConfig,
            $this->loggingEnabled,
            $this->authenticator,
            $mode,
        );
    }

    private function transport(): Transport
    {
        return new Transport(
            $this->http,
            $this->authenticator,
            $this->baseUrl,
            $this->requestModeConfig,
            $this->jobModeConfig,
            $this->logger,
            $this->loggingEnabled,
        );
    }
}
