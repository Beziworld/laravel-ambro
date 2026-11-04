<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;

/**
 * Result of CreateShipment: the main package number and one label per parcel.
 */
final readonly class CreatedShipment
{
    /**
     * @param list<Label> $labels
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $packageNumber,
        public array $labels,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $node CreateShipmentResult element.
     */
    public static function fromSoap(array $node): self
    {
        $labels = array_map(Label::fromSoap(...), Soap::items($node['ParcelData'] ?? null, 'Waybill'));
        $raw = $node;
        unset($raw['ParcelData']);

        return new self(
            packageNumber: Soap::string($node, 'PackageNo') ?? '',
            labels: $labels,
            raw: $raw,
        );
    }

    /**
     * Parcel numbers of the shipment, as printed on the labels.
     *
     * @return list<string>
     */
    public function parcelNumbers(): array
    {
        return array_values(array_filter(array_map(static fn(Label $label): ?string => $label->parcelId, $this->labels), static fn(?string $id): bool => $id !== null));
    }
}
