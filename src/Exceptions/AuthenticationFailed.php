<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Exceptions;

/**
 * Raised when the API rejects the credentials (responseCode 401, 2 or 3) or answers HTTP 401.
 */
final class AuthenticationFailed extends AmbroException
{
    public function __construct(string $message, private readonly string $operation, ?int $errorCode = null)
    {
        parent::__construct($message, $errorCode);
    }

    public static function for(string $operation, ?int $errorCode): self
    {
        return new self(
            sprintf('Ambro Express rejected the credentials (%s). Check the username, the password and the environment.', $operation),
            $operation,
            $errorCode,
        );
    }

    public function operation(): string
    {
        return $this->operation;
    }
}
