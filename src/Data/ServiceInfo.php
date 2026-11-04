<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;

/**
 * Service enabled for the account (GetServices, GetServicesInfo, GetAvailableServices).
 */
final readonly class ServiceInfo
{
    /**
     * @param array<string, string> $additionalServices Additional service code to its description (GetServicesInfo only).
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $additionalServices = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $node Service or ServiceInfo element.
     */
    public static function fromSoap(array $node): self
    {
        $additional = [];

        foreach (Soap::items($node['AdditionalServicesInfo'] ?? null, 'AdditionalServiceInfo') as $item) {
            $code = Soap::string($item, 'Code');

            if ($code !== null) {
                $additional[$code] = Soap::string($item, 'Description') ?? $code;
            }
        }

        return new self(
            id: Soap::int($node, 'ID') ?? 0,
            name: Soap::string($node, 'Name') ?? '',
            additionalServices: $additional,
            raw: $node,
        );
    }

    public function supports(string $additionalService): bool
    {
        return array_key_exists(strtoupper($additionalService), $this->additionalServices);
    }
}
