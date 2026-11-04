<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Exceptions;

/**
 * Raised when the API answers with a non-zero responseCode, a SOAP fault or an unexpected HTTP status.
 *
 * errorCode() holds the responseCode from the documented error list (for
 * example 1005 unknown ServiceId, 1036 invalid recipient city and postal
 * code combination, 1049 pickup time invalid for the location) and
 * description() the responseDescription returned with it.
 */
final class ApiException extends AmbroException
{
    public function __construct(
        string $message,
        private readonly string $operation,
        ?int $errorCode = null,
        private readonly ?string $description = null,
        private readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message, $errorCode);
    }

    public static function response(string $operation, int $code, ?string $description): self
    {
        return new self(
            sprintf('Ambro Express %s failed (%d): %s', $operation, $code, $description ?? 'no description'),
            $operation,
            $code,
            $description,
        );
    }

    public static function fault(string $operation, string $message, ?int $httpStatus): self
    {
        return new self(sprintf('Ambro Express %s failed: %s', $operation, $message), $operation, null, $message, $httpStatus);
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
