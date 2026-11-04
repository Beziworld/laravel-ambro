<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Enums;

/**
 * Packaging types accepted by the API, with the documented weight limits.
 */
enum ParcelType: string
{
    case Package = 'Package';
    case Palette = 'Palette';
    case LongPackage = 'LongPackage';
    case Size20 = 'Size20';
    case Furniture = 'Furniture';

    /**
     * Maximum weight of a single parcel in kilograms (error codes 1029, 1030, 1031 and 1033); null when not documented.
     */
    public function maxWeight(): ?float
    {
        return match ($this) {
            self::Package, self::LongPackage => 50.0,
            self::Palette => 200.0,
            self::Size20 => 20.0,
            self::Furniture => null,
        };
    }
}
