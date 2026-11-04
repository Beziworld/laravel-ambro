<?php

declare(strict_types=1);

use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Data\Parcel;
use Beziworld\Ambro\Enums\AdditionalService;
use Beziworld\Ambro\Enums\LabelFormat;
use Beziworld\Ambro\Enums\Service;
use Beziworld\Ambro\Exceptions\ApiException;
use Beziworld\Ambro\Exceptions\AuthenticationFailed;
use Beziworld\Ambro\Exceptions\ConnectionFailed;
use Beziworld\Ambro\Exceptions\InvalidShipment;
use Beziworld\Ambro\Facades\Ambro;
use Beziworld\Ambro\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function recipient(): Location
{
    return new Location('Anna Kowalska', 'ul. Długa 5/3', 'Kraków', '30-001', 'Anna Kowalska', '600700800', 'anna@example.com', privatePerson: true);
}

function createdResponse(): string
{
    return TestCase::asmx('CreateShipment', '<PackageNo>AMB0001234567</PackageNo><ParcelData>'
        . '<Waybill><ParcelID>AMB0001234567001</ParcelID><MimeFormat>PDF</MimeFormat><MimeData>' . base64_encode('%PDF-1 first') . '</MimeData></Waybill>'
        . '<Waybill><ParcelID>AMB0001234567002</ParcelID><MimeFormat>PDF</MimeFormat><MimeData>' . base64_encode('%PDF-1 second') . '</MimeData></Waybill>'
        . '</ParcelData>');
}

it('creates a shipment with the builder and returns the package number and labels', function (): void {
    TestCase::fakeAmbro(['CreateShipment' => createdResponse()]);

    $shipment = Ambro::shipment()
        ->to(recipient())
        ->package(12.5, 60, 40, 30, 'Books', 'ORDER-1')
        ->package(4)
        ->cashOnDelivery('149,99', 'PL61 1090 1014 0000 0712 1981 2874')
        ->insurance(500)
        ->currency('pln')
        ->with(AdditionalService::Sms, 'email')
        ->content('Books')
        ->costCenter('MPK-7')
        ->reference('ORDER-1')
        ->send();

    expect($shipment->packageNumber)->toBe('AMB0001234567')
        ->and($shipment->labels)->toHaveCount(2)
        ->and($shipment->labels[0]->content)->toBe('%PDF-1 first')
        ->and($shipment->labels[0]->filename())->toBe('label-AMB0001234567001.pdf')
        ->and($shipment->parcelNumbers())->toBe(['AMB0001234567001', 'AMB0001234567002'])
        ->and($shipment->raw)->not->toHaveKey('ParcelData');

    Http::assertSent(function (Request $request): bool {
        $root = '/soap:Envelope/soap:Body/t:CreateShipment';

        return $request->url() === TestCase::SANDBOX
            && $request->method() === 'POST'
            && $request->header('SOAPAction')[0] === '"http://tempuri.org/CreateShipment"'
            && str_starts_with($request->header('Content-Type')[0], 'text/xml')
            && TestCase::at($request, $root . '/t:token/t:UserName') === 'demo'
            && TestCase::at($request, $root . '/t:token/t:Password') === 'demo'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:ServiceId') === '38'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:ShipFrom/t:Name') === 'ACME Sp. z o.o.'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:ShipFrom/t:Contact') === '500600700'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:ShipTo/t:City') === 'Kraków'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:ShipTo/t:IsPrivatePerson') === 'true'
            && TestCase::nodes($request, $root . '/t:ShipmentRequest/t:Parcels/t:Parcel') === 2
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:Parcels/t:Parcel[1]/t:Type') === 'Package'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:Parcels/t:Parcel[1]/t:Weight') === '12.5'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:Parcels/t:Parcel[1]/t:D') === '60'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:Parcels/t:Parcel[1]/t:W') === '40'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:Parcels/t:Parcel[1]/t:S') === '30'
            && TestCase::nodes($request, $root . '/t:ShipmentRequest/t:Parcels/t:Parcel[2]/t:D') === 0
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:Currency/t:Code') === 'PLN'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:COD/t:Amount') === '149.99'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:COD/t:RetAccountNo') === '61109010140000071219812874'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:InsuranceAmount') === '500.00'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:AdditionalServices/t:AdditionalService[2]/t:Code') === 'EMAIL'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:LabelFormat') === 'PDF'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:MPK') === 'MPK-7'
            && TestCase::at($request, $root . '/t:ShipmentRequest/t:ReferenceNumber') === 'ORDER-1';
    });
});

it('serialises elements in the WSDL sequence order', function (): void {
    TestCase::fakeAmbro(['CreateShipment' => createdResponse()]);

    Ambro::shipment()->to(recipient())->package(1)->insurance(100)->currency('PLN')->send();

    Http::assertSent(function (Request $request): bool {
        $names = [];

        foreach (TestCase::xpath($request)->query('/soap:Envelope/soap:Body/t:CreateShipment/t:ShipmentRequest/*') ?: [] as $node) {
            $names[] = $node->localName;
        }

        return $names === ['ServiceId', 'ShipFrom', 'ShipTo', 'Parcels', 'Currency', 'InsuranceAmount', 'LabelFormat'];
    });
});

it('sends pallets with the furniture service and an A4 label', function (): void {
    TestCase::fakeAmbro(['CreateShipment' => createdResponse()]);

    Ambro::shipment()
        ->to(recipient())
        ->service(Service::Furniture)
        ->palette(180, 120, 150, 80)
        ->palette(90)
        ->labelFormat(LabelFormat::PdfA4)
        ->with(AdditionalService::CarryIn, AdditionalService::Assembly)
        ->send();

    Http::assertSent(fn(Request $request): bool => TestCase::at($request, '//t:ServiceId') === '200'
        && TestCase::at($request, '//t:Parcel[2]/t:Type') === 'Palette'
        && TestCase::at($request, '//t:LabelFormat') === 'PDFA4'
        && TestCase::at($request, '//t:AdditionalService[1]/t:Code') === 'W');
});

it('accepts a prepared Shipment object', function (): void {
    TestCase::fakeAmbro(['CreateShipment' => createdResponse()]);

    $shipment = Ambro::shipment()->to(recipient())->parcel(Parcel::package(2))->build();

    expect(Ambro::createShipment($shipment)->packageNumber)->toBe('AMB0001234567');
});

it('rejects invalid shipments before sending', function (callable $build, string $message): void {
    Http::fake();

    expect(fn() => $build(Ambro::shipment()->to(recipient()))->send())->toThrow(InvalidShipment::class, $message);

    Http::assertNothingSent();
})->with([
    'no parcels' => [fn($b) => $b, 'at least one parcel'],
    'zero weight' => [fn($b) => $b->package(0), 'must weigh more than 0'],
    'package over 50 kg' => [fn($b) => $b->package(50.5), 'exceeds 50 kg'],
    'palette over 200 kg' => [fn($b) => $b->palette(201), 'exceeds 200 kg'],
    'mixed palette' => [fn($b) => $b->palette(100)->package(5), 'cannot be mixed'],
    'insurance below COD' => [fn($b) => $b->package(1)->cashOnDelivery(300, '61109010140000071219812874')->insurance(100), 'greater than or equal to the COD'],
    'COD without account' => [fn($b) => $b->package(1)->cashOnDelivery(10, 'n/a'), 'bank account'],
    'duplicated service' => [fn($b) => $b->package(1)->with('SMS', AdditionalService::Sms), 'more than once'],
    'foreign to foreign' => [fn($b) => $b->package(1)->from(new Location('A', 'B', 'Berlin', '10115', 'P', '1', 'a@b.de', 'DE'))->to(new Location('A', 'B', 'Praha', '11000', 'P', '1', 'a@b.cz', 'CZ')), 'must be Poland'],
    'incomplete recipient' => [fn($b) => $b->package(1)->to(new Location('Anna', 'ul. Długa 5', 'Kraków', '30-001', 'Anna', '', 'anna@example.com')), 'recipient is missing phone'],
]);

it('requires a sender when none is configured', function (): void {
    config()->set('ambro.sender', []);
    Ambro::purge();

    expect(fn() => Ambro::shipment()->to(recipient())->package(1)->build())->toThrow(InvalidShipment::class, 'sender is not set');
});

it('maps a non-zero responseCode to ApiException', function (): void {
    TestCase::fakeAmbro(['CreateShipment' => TestCase::asmx('CreateShipment', '', 1036, 'ShipTo combination of city, postal code and country is invalid')]);

    try {
        Ambro::shipment()->to(recipient())->package(1)->send();
        $this->fail('Expected exception');
    } catch (ApiException $exception) {
        expect($exception->errorCode())->toBe(1036)
            ->and($exception->operation())->toBe('CreateShipment')
            ->and($exception->description())->toContain('postal code')
            ->and($exception->getMessage())->toContain('(1036)');
    }
});

it('maps authentication response codes to AuthenticationFailed', function (): void {
    TestCase::fakeAmbro(['CreateShipment' => TestCase::asmx('CreateShipment', '', 401, 'Authentication failed')]);

    expect(fn() => Ambro::shipment()->to(recipient())->package(1)->send())->toThrow(AuthenticationFailed::class);
});

it('maps a SOAP fault to ApiException with the HTTP status', function (): void {
    Http::fake([TestCase::SANDBOX => Http::response(TestCase::fault('Server was unable to process request.'), 500)]);

    try {
        Ambro::shipment()->to(recipient())->package(1)->send();
        $this->fail('Expected exception');
    } catch (ApiException $exception) {
        expect($exception->httpStatus())->toBe(500)
            ->and($exception->getMessage())->toContain('unable to process');
    }
});

it('never retries shipment creation after a connection error', function (): void {
    $attempts = 0;
    Http::fake(function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('timed out');
    });

    expect(fn() => Ambro::shipment()->to(recipient())->package(1)->send())->toThrow(ConnectionFailed::class)
        ->and($attempts)->toBe(1);
});

it('lists the services available for a route', function (): void {
    TestCase::fakeAmbro(['GetAvailableServices' => TestCase::asmx('GetAvailableServices', '<AvailableServices><Service><ID>38</ID><Name>Standard</Name></Service><Service><ID>200</ID><Name>MEBEL</Name></Service></AvailableServices>')]);

    $services = Ambro::availableServices(Ambro::shipment()->to(recipient())->package(3), Carbon\CarbonImmutable::parse('2026-11-05 09:00', 'Europe/Warsaw'));

    expect($services)->toHaveCount(2)
        ->and($services[1]->id)->toBe(200)
        ->and($services[1]->name)->toBe('MEBEL');

    Http::assertSent(function (Request $request): bool {
        $root = '/soap:Envelope/soap:Body/t:GetAvailableServices/t:getAvailableServicesRequest';

        return TestCase::at($request, $root . '/t:ReadyDate') === '2026-11-05T09:00:00'
            && TestCase::nodes($request, $root . '/t:ServiceId') === 0
            && TestCase::nodes($request, $root . '/t:LabelFormat') === 0
            && TestCase::at($request, $root . '/t:ShipTo/t:PostCode') === '30-001';
    });
});
