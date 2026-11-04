<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;
use Beziworld\Ambro\Support\Values;
use Carbon\CarbonImmutable;

/**
 * One of the nearest days on which a courier can collect the shipments (GetAvailablePickups).
 *
 * The pickup window passed to callPickup() must start at or after
 * earliestReadyAt, end at or before latestPickupAt and last at least
 * minimumInterval minutes.
 */
final readonly class PickupWindow
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?CarbonImmutable $date,
        public ?CarbonImmutable $earliestReadyAt,
        public ?CarbonImmutable $latestPickupAt,
        public int $minimumInterval,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $node AvailablePickupDay element.
     */
    public static function fromSoap(array $node): self
    {
        return new self(
            date: Values::parseDate(Soap::string($node, 'Date')),
            earliestReadyAt: Values::parseDate(Soap::string($node, 'MinReadyDate')),
            latestPickupAt: Values::parseDate(Soap::string($node, 'MaxPickupDate')),
            minimumInterval: Soap::int($node, 'MinimumInterval') ?? 0,
            raw: $node,
        );
    }
}
