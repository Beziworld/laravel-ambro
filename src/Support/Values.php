<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Conversions between PHP values and the representation used by the API.
 *
 * The service runs in Polish local time and exchanges xsd:dateTime values
 * without an offset; "0001-01-01T00:00:00" is the .NET default and means
 * that no date is set. Amounts and weights are sent as decimal strings with
 * a dot separator.
 */
final class Values
{
    public const string TIMEZONE = 'Europe/Warsaw';

    /**
     * Formats a date as a local xsd:dateTime without an offset.
     */
    public static function dateTime(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i:s');
    }

    /**
     * Parses an xsd:dateTime returned by the API; empty and default values give null.
     */
    public static function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '' || str_starts_with($value, '0001-01-01')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, self::TIMEZONE)->setTimezone(self::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalises an amount or a measure to a decimal string with a fixed scale.
     *
     * @throws \InvalidArgumentException When the value is not numeric.
     */
    public static function decimal(string|int|float $value, int $scale = 2): string
    {
        $normalized = is_string($value) ? str_replace([' ', ','], ['', '.'], trim($value)) : $value;

        if (! is_numeric($normalized)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a numeric value.', $value));
        }

        return number_format((float) $normalized, $scale, '.', '');
    }

    /**
     * Formats a measure without trailing zeros (for example 2.5 or 120).
     */
    public static function measure(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
