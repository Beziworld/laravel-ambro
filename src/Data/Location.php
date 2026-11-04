<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;

/**
 * Sender, recipient or pickup address (Location).
 *
 * The API requires the name, street address, city, postal code, country,
 * contact person, phone and e-mail for the sender, the recipient and the
 * pickup location (error codes 1007 to 1024 and 1038 to 1045). The country
 * is an ISO 3166-1 alpha-2 code; supported countries are PL, DE, AT, BE,
 * NL, LU, GB, CZ, SK, HU and RO, and one side must be Poland.
 */
final readonly class Location
{
    public function __construct(
        public string $name,
        public string $address,
        public string $city,
        public string $postCode,
        public string $person,
        public string $phone,
        public string $email,
        public string $countryCode = 'PL',
        public bool $privatePerson = false,
        public ?string $pointId = null,
    ) {}

    /**
     * Creates a location from configuration-style keys (name, address, city, post_code, person, phone, email, country_code, private_person, point_id).
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn(string $key): string => is_scalar($data[$key] ?? null) ? trim((string) $data[$key]) : '';

        return new self(
            name: $string('name'),
            address: $string('address'),
            city: $string('city'),
            postCode: $string('post_code'),
            person: $string('person'),
            phone: $string('phone'),
            email: $string('email'),
            countryCode: strtoupper($string('country_code') !== '' ? $string('country_code') : 'PL'),
            privatePerson: filter_var($data['private_person'] ?? false, FILTER_VALIDATE_BOOL),
            pointId: $string('point_id') !== '' ? $string('point_id') : null,
        );
    }

    /**
     * Creates a location from a parsed API response node.
     *
     * @param array<array-key, mixed> $node
     */
    public static function fromSoap(array $node): self
    {
        return new self(
            name: Soap::string($node, 'Name') ?? '',
            address: Soap::string($node, 'Address') ?? '',
            city: Soap::string($node, 'City') ?? '',
            postCode: Soap::string($node, 'PostCode') ?? '',
            person: Soap::string($node, 'Person') ?? '',
            phone: Soap::string($node, 'Contact') ?? '',
            email: Soap::string($node, 'Email') ?? '',
            countryCode: Soap::string($node, 'CountryCode') ?? 'PL',
            privatePerson: Soap::string($node, 'IsPrivatePerson') === 'true',
            pointId: Soap::string($node, 'PointId'),
        );
    }

    /**
     * Names of required fields that are empty.
     *
     * @return list<string>
     */
    public function missingFields(): array
    {
        $fields = [
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'postCode' => $this->postCode,
            'countryCode' => $this->countryCode,
            'person' => $this->person,
            'phone' => $this->phone,
            'email' => $this->email,
        ];

        return array_keys(array_filter($fields, static fn(string $value): bool => trim($value) === ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSoap(): array
    {
        return [
            'PointId' => $this->pointId,
            'Name' => $this->name,
            'Address' => $this->address,
            'City' => $this->city,
            'PostCode' => $this->postCode,
            'CountryCode' => strtoupper($this->countryCode),
            'Person' => $this->person,
            'Contact' => $this->phone,
            'Email' => $this->email,
            'IsPrivatePerson' => $this->privatePerson,
        ];
    }
}
