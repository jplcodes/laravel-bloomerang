<?php

namespace JplCodes\Bloomerang\Exceptions;

use RuntimeException;

/**
 * Base class for every exception this package throws.
 */
abstract class BloomerangException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $correlationId = null,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }
}
