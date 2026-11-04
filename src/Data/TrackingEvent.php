<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;
use Beziworld\Ambro\Support\Values;
use Carbon\CarbonImmutable;

/**
 * One status of a package (OrderStatus).
 *
 * code is one of the documented status codes, for example PKL (taken from
 * the customer by the courier), PDD (out for delivery), AWI (notice left),
 * DOR (delivered), ZWR or ZDN (returned) and ANU (cancelled).
 */
final readonly class TrackingEvent
{
    /** @var list<string> */
    public const array DELIVERED = ['DOR', 'DORPW', 'OZP'];

    /** @var list<string> */
    public const array RETURNED = ['ZWR', 'ZDN', 'ZDN1', 'ZZP', 'AZPO'];

    /** @var list<string> */
    public const array CANCELLED = ['ANU', 'ANZ'];

    /**
     * @param array<string, string> $descriptions Description per language: PL, EN, DE, CZ, SK, HU, RO.
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?string $packageNumber,
        public ?string $code,
        public ?string $description,
        public array $descriptions,
        public ?CarbonImmutable $occurredAt,
        public ?string $parameter,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $node OrderStatus element.
     */
    public static function fromSoap(array $node): self
    {
        $descriptions = array_filter([
            'PL' => Soap::string($node, 'Description'),
            'EN' => Soap::string($node, 'DescriptionEN'),
            'DE' => Soap::string($node, 'DescriptionDE'),
            'CZ' => Soap::string($node, 'DescriptionCZ'),
            'SK' => Soap::string($node, 'DescriptionSK'),
            'HU' => Soap::string($node, 'DescriptionHU'),
            'RO' => Soap::string($node, 'DescriptionRO'),
        ], static fn(?string $value): bool => $value !== null);

        return new self(
            packageNumber: Soap::string($node, 'PackageNo'),
            code: Soap::string($node, 'Code'),
            description: Soap::string($node, 'Description'),
            descriptions: $descriptions,
            occurredAt: Values::parseDate(Soap::string($node, 'EventTimestamp')),
            parameter: Soap::string($node, 'EventParam'),
            raw: $node,
        );
    }

    /**
     * Description in the given language, falling back to Polish.
     */
    public function description(string $language = 'PL'): ?string
    {
        return $this->descriptions[strtoupper($language)] ?? $this->description;
    }

    public function isDelivered(): bool
    {
        return in_array($this->code, self::DELIVERED, true);
    }

    public function isReturned(): bool
    {
        return in_array($this->code, self::RETURNED, true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->code, self::CANCELLED, true);
    }
}
