<?php

namespace JplCodes\Bloomerang\Exceptions;

/**
 * Bloomerang throttled the call (HTTP 429).
 */
final class RateLimited extends BloomerangException
{
    public function __construct(
        string $message,
        ?string $correlationId,
        ?int $status,
        public readonly ?int $retryAfterSeconds,
    ) {
        parent::__construct($message, $correlationId, $status);
    }
}
