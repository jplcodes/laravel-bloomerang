<?php

namespace JplCodes\Bloomerang\Exceptions;

use RuntimeException;

/**
 * Base class for every exception this package throws.
 *
 * `$errorCode` is the `ErrorCode` from Bloomerang's error body (`{Message, ErrorCode}`), set only when the body
 * carried an integer one. The body's `Message` is deliberately never kept, because it could carry personal data.
 */
abstract class BloomerangException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $correlationId = null,
        public readonly ?int $status = null,
        public readonly ?int $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
