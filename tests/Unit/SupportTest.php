<?php

declare(strict_types=1);

use Beziworld\Ambro\Data\CashOnDelivery;
use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Enums\ParcelType;
use Beziworld\Ambro\Support\Soap;
use Beziworld\Ambro\Support\Values;
use Beziworld\Ambro\Tests\TestCase;
use Carbon\CarbonImmutable;

it('builds a qualified document/literal envelope', function (): void {
    $xml = Soap::envelope('http://tempuri.org/', 'GetLabel', [
        'token' => ['UserName' => 'demo', 'Password' => 'demo'],
        'request' => ['PackageNo' => ['string' => ['A', 'B']]],
        'Skip' => null,
        'Flag' => false,
    ]);

    $document = new DOMDocument();
    $document->loadXML($xml);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('t', 'http://tempuri.org/');
    $xpath->registerNamespace('soap', Soap::ENVELOPE_NS);

    expect($xpath->evaluate('count(/soap:Envelope/soap:Body/t:GetLabel/t:request/t:PackageNo/t:string)'))->toBe(2.0)
        ->and($xpath->evaluate('string(//t:token/t:UserName)'))->toBe('demo')
        ->and($xpath->evaluate('count(//t:Skip)'))->toBe(0.0)
        ->and($xpath->evaluate('string(//t:Flag)'))->toBe('false');
});

it('parses SOAP faults and invalid XML', function (): void {
    expect(Soap::parse(TestCase::fault('Boom'))['fault'])->toBe(['code' => 'soap:Server', 'message' => 'Boom'])
        ->and(Soap::parse('not xml')['fault']['message'] ?? null)->toBe('The response is not valid XML.')
        ->and(Soap::parse('<a/>')['fault']['message'] ?? null)->toBe('The response has no SOAP body.');
});

it('parses repeated and nil elements', function (): void {
    $parsed = Soap::parse(TestCase::asmx('GetTracking', '<Status><OrderStatus><Code>A</Code></OrderStatus></Status><DatePicked xsi:nil="true" />'));
    $result = $parsed['body']['GetTrackingResult'];

    expect(Soap::items($result['Status'], 'OrderStatus'))->toBe([['Code' => 'A']])
        ->and($result['DatePicked'])->toBeNull()
        ->and(Soap::int($result, 'responseCode'))->toBe(0)
        ->and(Soap::string($result, 'missing'))->toBeNull()
        ->and(Soap::items('text'))->toBe([]);
});

it('formats and parses API dates in Polish local time', function (): void {
    expect(Values::dateTime(CarbonImmutable::parse('2026-07-01 08:00', 'UTC')))->toBe('2026-07-01T10:00:00')
        ->and(Values::parseDate('0001-01-01T00:00:00'))->toBeNull()
        ->and(Values::parseDate(''))->toBeNull()
        ->and(Values::parseDate('garbage'))->toBeNull()
        ->and(Values::parseDate('2026-11-05T13:40:00')?->toIso8601String())->toBe('2026-11-05T13:40:00+01:00');
});

it('normalises decimals and measures', function (): void {
    expect(Values::decimal('1 234,5'))->toBe('1234.50')
        ->and(Values::decimal(10))->toBe('10.00')
        ->and(Values::measure(2.50))->toBe('2.5')
        ->and(Values::measure(120.0))->toBe('120')
        ->and(fn() => Values::decimal('abc'))->toThrow(InvalidArgumentException::class);
});

it('keeps only digits of the COD account number', function (): void {
    $cod = new CashOnDelivery(99.9, 'PL 61 1090-1014');

    expect($cod->toSoap())->toBe(['Amount' => '99.90', 'RetAccountNo' => '6110901014']);
});

it('documents parcel weight limits', function (): void {
    expect(ParcelType::Package->maxWeight())->toBe(50.0)
        ->and(ParcelType::Palette->maxWeight())->toBe(200.0)
        ->and(ParcelType::Size20->maxWeight())->toBe(20.0)
        ->and(ParcelType::Furniture->maxWeight())->toBeNull();
});

it('builds locations from configuration and API nodes', function (): void {
    $location = Location::fromArray(['name' => ' ACME ', 'country_code' => 'de', 'private_person' => '1']);
    $parsed = Location::fromSoap(['Name' => 'X', 'Contact' => '123', 'IsPrivatePerson' => 'true', 'CountryCode' => 'CZ']);

    expect($location->name)->toBe('ACME')
        ->and($location->countryCode)->toBe('DE')
        ->and($location->privatePerson)->toBeTrue()
        ->and($location->missingFields())->toBe(['address', 'city', 'postCode', 'person', 'phone', 'email'])
        ->and($parsed->phone)->toBe('123')
        ->and($parsed->privatePerson)->toBeTrue()
        ->and($parsed->countryCode)->toBe('CZ');
});
