<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Enums\ParcelType;
use Beziworld\Ambro\Support\Values;

/**
 * One handling unit of a shipment: weight in kilograms, dimensions in centimetres.
 *
 * The API names the dimensions D (length), W (height) and S (width).
 */
final readonly class Parcel
{
    public function __construct(
        public float $weight,
        public ParcelType $type = ParcelType::Package,
        public ?float $length = null,
        public ?float $height = null,
        public ?float $width = null,
        public ?string $content = null,
        public ?string $reference = null,
    ) {}

    public static function package(float $weight, ?float $length = null, ?float $height = null, ?float $width = null, ?string $content = null, ?string $reference = null): self
    {
        return new self($weight, ParcelType::Package, $length, $height, $width, $content, $reference);
    }

    public static function palette(float $weight, ?float $length = null, ?float $height = null, ?float $width = null, ?string $content = null, ?string $reference = null): self
    {
        return new self($weight, ParcelType::Palette, $length, $height, $width, $content, $reference);
    }

    public static function furniture(float $weight, ?float $length = null, ?float $height = null, ?float $width = null, ?string $content = null, ?string $reference = null): self
    {
        return new self($weight, ParcelType::Furniture, $length, $height, $width, $content, $reference);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSoap(): array
    {
        return [
            'Type' => $this->type->value,
            'Weight' => Values::measure($this->weight),
            'D' => $this->length !== null ? Values::measure($this->length) : null,
            'W' => $this->height !== null ? Values::measure($this->height) : null,
            'S' => $this->width !== null ? Values::measure($this->width) : null,
            'ContentDescription' => $this->content,
            'Reference' => $this->reference,
        ];
    }
}
