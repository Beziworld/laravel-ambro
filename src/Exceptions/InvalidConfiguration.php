<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Exceptions;

/**
 * Raised when the package configuration is incomplete or refers to an unknown connection.
 */
final class InvalidConfiguration extends AmbroException
{
    public static function missing(string $key, string $env): self
    {
        return new self(sprintf('Ambro Express "%s" is not configured. Set %s in the environment.', $key, $env));
    }

    public static function unknownConnection(string $name): self
    {
        return new self(sprintf('Ambro Express connection "%s" is not defined in config/ambro.php.', $name));
    }
}
