<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Exceptions;

/**
 * Raised before any request is sent when a shipment or pickup breaks a documented API rule.
 */
final class InvalidShipment extends AmbroException
{
    public static function because(string $reason): self
    {
        return new self('Invalid Ambro Express shipment: ' . $reason);
    }
}
