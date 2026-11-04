<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for every error raised by the Ambro Express integration.
 *
 * errorCode() returns the responseCode reported by the API when there is
 * one (for example 1029 when a parcel of type Package exceeds 50 kg).
 */
class AmbroException extends RuntimeException
{
    public function __construct(string $message = '', private readonly ?int $errorCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $errorCode ?? 0, $previous);
    }

    public function errorCode(): ?int
    {
        return $this->errorCode;
    }
}
