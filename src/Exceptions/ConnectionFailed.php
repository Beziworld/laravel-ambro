<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Exceptions;

use Throwable;

/**
 * Raised when the API cannot be reached (DNS, TLS, timeout).
 *
 * Write operations (CreateShipment, CallPickup, cancellations) are never
 * retried automatically: after a timeout of createShipment() look the
 * shipment up by its reference in the Ambro panel before sending it again.
 */
final class ConnectionFailed extends AmbroException
{
    public function __construct(string $message, private readonly string $operation, ?Throwable $previous = null)
    {
        parent::__construct($message, null, $previous);
    }

    public static function during(string $operation, Throwable $previous): self
    {
        return new self(sprintf('Could not connect to Ambro Express (%s): %s', $operation, $previous->getMessage()), $operation, $previous);
    }

    public function operation(): string
    {
        return $this->operation;
    }
}
