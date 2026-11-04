<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;
use Beziworld\Ambro\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Tracking history of a package (GetTracking).
 */
final readonly class Tracking
{
    /**
     * @param list<TrackingEvent> $events Events in chronological order.
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $packageNumber,
        public array $events,
        public ?TrackingEvent $current,
        public ?CarbonImmutable $pickedUpAt,
        public ?CarbonImmutable $deliveredAt,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $node GetTrackingResult element.
     */
    public static function fromSoap(string $packageNumber, array $node): self
    {
        $events = array_map(TrackingEvent::fromSoap(...), Soap::items($node['Status'] ?? null, 'OrderStatus'));
        usort($events, static fn(TrackingEvent $a, TrackingEvent $b): int => ($a->occurredAt?->getTimestamp() ?? 0) <=> ($b->occurredAt?->getTimestamp() ?? 0));
        $current = is_array($node['CurrentStatus'] ?? null) ? TrackingEvent::fromSoap($node['CurrentStatus']) : null;

        return new self(
            packageNumber: $packageNumber,
            events: $events,
            current: $current ?? ($events !== [] ? $events[count($events) - 1] : null),
            pickedUpAt: Values::parseDate(Soap::string($node, 'DatePicked')),
            deliveredAt: Values::parseDate(Soap::string($node, 'DateDelivered')),
            raw: $node,
        );
    }

    public function isDelivered(): bool
    {
        return $this->current?->isDelivered() ?? false;
    }
}
