<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Exceptions\InvalidShipment;
use Beziworld\Ambro\Support\Values;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Courier pickup order (CallPickupRequest).
 *
 * The request needs either the numbers of already created shipments or a
 * list of parcels. The window runs from readyAt (goods ready) to
 * maxPickupAt (latest pickup); it cannot end on a Sunday (error 1054) and
 * must respect the minimum interval returned by availablePickups().
 */
final readonly class Pickup
{
    public CarbonImmutable $readyAt;

    public CarbonImmutable $maxPickupAt;

    /**
     * @param list<string> $packageNumbers
     * @param list<Parcel> $parcels
     */
    public function __construct(
        public Location $location,
        DateTimeInterface $readyAt,
        DateTimeInterface $maxPickupAt,
        public array $packageNumbers = [],
        public array $parcels = [],
        public ?float $totalWeight = null,
        public ?int $totalQuantity = null,
    ) {
        $this->readyAt = CarbonImmutable::instance($readyAt)->setTimezone(Values::TIMEZONE);
        $this->maxPickupAt = CarbonImmutable::instance($maxPickupAt)->setTimezone(Values::TIMEZONE);
    }

    /**
     * @throws InvalidShipment When a documented rule is broken.
     */
    public function validate(): void
    {
        $missing = $this->location->missingFields();

        if ($missing !== []) {
            throw InvalidShipment::because(sprintf('the pickup location is missing %s.', implode(', ', $missing)));
        }

        if ($this->packageNumbers === [] && $this->parcels === []) {
            throw InvalidShipment::because('a pickup needs package numbers or parcels.');
        }

        if ($this->maxPickupAt->lessThanOrEqualTo($this->readyAt)) {
            throw InvalidShipment::because('the latest pickup time must be after the ready time.');
        }

        if ($this->maxPickupAt->isSunday()) {
            throw InvalidShipment::because('the latest pickup time cannot fall on a Sunday.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toSoap(): array
    {
        $weight = $this->totalWeight ?? ($this->parcels !== [] ? array_sum(array_map(static fn(Parcel $parcel): float => $parcel->weight, $this->parcels)) : null);
        $quantity = $this->totalQuantity ?? ($this->parcels !== [] ? count($this->parcels) : count($this->packageNumbers));

        return [
            'PickupLocation' => $this->location->toSoap(),
            'ReadyDate' => Values::dateTime($this->readyAt),
            'MaxPickupDate' => Values::dateTime($this->maxPickupAt),
            'PackageNo' => $this->packageNumbers !== [] ? ['string' => $this->packageNumbers] : null,
            'Parcels' => $this->parcels !== [] ? ['Parcel' => array_map(static fn(Parcel $parcel): array => $parcel->toSoap(), $this->parcels)] : null,
            'TotalWeight' => $weight !== null ? Values::measure($weight) : null,
            'TotalQuantity' => $quantity > 0 ? $quantity : null,
        ];
    }
}
