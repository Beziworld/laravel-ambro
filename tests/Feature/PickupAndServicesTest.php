<?php

declare(strict_types=1);

use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Data\Parcel;
use Beziworld\Ambro\Data\Pickup;
use Beziworld\Ambro\Exceptions\ApiException;
use Beziworld\Ambro\Exceptions\InvalidShipment;
use Beziworld\Ambro\Facades\Ambro;
use Beziworld\Ambro\Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('lists the services and their additional services', function (): void {
    TestCase::fakeAmbro(['GetServicesInfo' => TestCase::asmx('GetServicesInfo', '<Services><ServiceInfo><ID>38</ID><Name>Standard</Name><AdditionalServicesInfo>'
        . '<AdditionalServiceInfo><Code>SMS</Code><Description>Serwis SMS</Description></AdditionalServiceInfo>'
        . '<AdditionalServiceInfo><Code>ROD</Code><Description>Zwrot dokumentów</Description></AdditionalServiceInfo>'
        . '</AdditionalServicesInfo></ServiceInfo></Services>')]);

    $services = Ambro::servicesInfo();

    expect($services)->toHaveCount(1)
        ->and($services[0]->id)->toBe(38)
        ->and($services[0]->additionalServices)->toBe(['SMS' => 'Serwis SMS', 'ROD' => 'Zwrot dokumentów'])
        ->and($services[0]->supports('rod'))->toBeTrue()
        ->and($services[0]->supports('ZNI'))->toBeFalse();

    Http::assertSent(fn(Request $request): bool => TestCase::nodes($request, '/soap:Envelope/soap:Body/t:GetServicesInfo/*') === 1
        && TestCase::at($request, '//t:GetServicesInfo/t:token/t:UserName') === 'demo');
});

it('lists the services of the account', function (): void {
    TestCase::fakeAmbro(['GetServices' => TestCase::asmx('GetServices', '<Services><Service><ID>38</ID><Name>Standard</Name></Service></Services>')]);

    expect(Ambro::services()[0]->name)->toBe('Standard');
});

it('returns the available pickup windows for the configured sender', function (): void {
    TestCase::fakeAmbro(['GetAvailablePickups' => TestCase::asmx('GetAvailablePickups', '<AvailablePickupDays>'
        . '<AvailablePickupDay><Date>2026-11-05T00:00:00</Date><MinReadyDate>2026-11-05T09:00:00</MinReadyDate><MaxPickupDate>2026-11-05T17:00:00</MaxPickupDate><MinimumInterval>120</MinimumInterval></AvailablePickupDay>'
        . '<AvailablePickupDay><Date>2026-11-06T00:00:00</Date><MinReadyDate>2026-11-06T08:00:00</MinReadyDate><MaxPickupDate>2026-11-06T16:00:00</MaxPickupDate><MinimumInterval>120</MinimumInterval></AvailablePickupDay>'
        . '</AvailablePickupDays>')]);

    $windows = Ambro::availablePickups(packageNumbers: ['AMB1']);

    expect($windows)->toHaveCount(2)
        ->and($windows[0]->earliestReadyAt?->format('H:i'))->toBe('09:00')
        ->and($windows[0]->latestPickupAt?->format('H:i'))->toBe('17:00')
        ->and($windows[0]->minimumInterval)->toBe(120);

    Http::assertSent(fn(Request $request): bool => TestCase::at($request, '//t:getAvailablePickupsRequest/t:PickupLocation/t:City') === 'Warszawa'
        && TestCase::at($request, '//t:getAvailablePickupsRequest/t:PackageNo/t:string') === 'AMB1'
        && TestCase::nodes($request, '//t:getAvailablePickupsRequest/t:Parcels') === 0);
});

it('orders a courier pickup for created shipments', function (): void {
    TestCase::fakeAmbro(['CallPickup' => TestCase::asmx('CallPickup', '<PickupNo>PU-555</PickupNo>')]);

    $sender = Ambro::config()->sender;
    expect($sender)->not->toBeNull();

    $number = Ambro::callPickup(new Pickup(
        location: $sender,
        readyAt: CarbonImmutable::parse('2026-11-05 10:00', 'Europe/Warsaw'),
        maxPickupAt: CarbonImmutable::parse('2026-11-05 15:00', 'Europe/Warsaw'),
        packageNumbers: ['AMB1', 'AMB2'],
        totalWeight: 14.5,
    ));

    expect($number)->toBe('PU-555');

    Http::assertSent(function (Request $request): bool {
        $root = '//t:CallPickup/t:callPickupRequest';

        return TestCase::at($request, $root . '/t:ReadyDate') === '2026-11-05T10:00:00'
            && TestCase::at($request, $root . '/t:MaxPickupDate') === '2026-11-05T15:00:00'
            && TestCase::nodes($request, $root . '/t:PackageNo/t:string') === 2
            && TestCase::at($request, $root . '/t:TotalWeight') === '14.5'
            && TestCase::at($request, $root . '/t:TotalQuantity') === '2';
    });
});

it('converts pickup times to Polish local time', function (): void {
    TestCase::fakeAmbro(['CallPickup' => TestCase::asmx('CallPickup', '<PickupNo>PU-1</PickupNo>')]);

    Ambro::callPickup(new Pickup(
        location: Ambro::config()->sender ?? throw new LogicException(),
        readyAt: CarbonImmutable::parse('2026-11-05 08:00', 'UTC'),
        maxPickupAt: CarbonImmutable::parse('2026-11-05 14:00', 'UTC'),
        parcels: [Parcel::palette(120), Parcel::palette(80)],
    ));

    Http::assertSent(fn(Request $request): bool => TestCase::at($request, '//t:ReadyDate') === '2026-11-05T09:00:00'
        && TestCase::at($request, '//t:TotalWeight') === '200'
        && TestCase::at($request, '//t:TotalQuantity') === '2'
        && TestCase::at($request, '//t:callPickupRequest/t:Parcels/t:Parcel[1]/t:Type') === 'Palette');
});

it('validates pickups before sending', function (Pickup $pickup, string $message): void {
    Http::fake();

    expect(fn() => Ambro::callPickup($pickup))->toThrow(InvalidShipment::class, $message);
    Http::assertNothingSent();
})->with([
    'nothing to collect' => fn() => [new Pickup(new Location('A', 'B', 'C', '00-001', 'P', '1', 'a@b.pl'), CarbonImmutable::parse('2026-11-05 10:00'), CarbonImmutable::parse('2026-11-05 12:00')), 'package numbers or parcels'],
    'reversed window' => fn() => [new Pickup(new Location('A', 'B', 'C', '00-001', 'P', '1', 'a@b.pl'), CarbonImmutable::parse('2026-11-05 12:00'), CarbonImmutable::parse('2026-11-05 10:00'), ['AMB1']), 'after the ready time'],
    'sunday' => fn() => [new Pickup(new Location('A', 'B', 'C', '00-001', 'P', '1', 'a@b.pl'), CarbonImmutable::parse('2026-11-08 10:00'), CarbonImmutable::parse('2026-11-08 12:00'), ['AMB1']), 'Sunday'],
    'incomplete location' => fn() => [new Pickup(new Location('A', '', 'C', '00-001', 'P', '1', 'a@b.pl'), CarbonImmutable::parse('2026-11-05 10:00'), CarbonImmutable::parse('2026-11-05 12:00'), ['AMB1']), 'missing address'],
]);

it('fails when the pickup response has no number', function (): void {
    TestCase::fakeAmbro(['CallPickup' => TestCase::asmx('CallPickup')]);

    expect(fn() => Ambro::callPickup(new Pickup(Ambro::config()->sender ?? throw new LogicException(), CarbonImmutable::parse('2026-11-05 10:00'), CarbonImmutable::parse('2026-11-05 12:00'), ['AMB1'])))
        ->toThrow(ApiException::class, 'no pickup number');
});

it('cancels pickups', function (): void {
    TestCase::fakeAmbro(['CancelPickup' => TestCase::asmx('CancelPickup')]);

    Ambro::cancelPickups('PU-555');

    Http::assertSent(fn(Request $request): bool => TestCase::at($request, '//t:cancelPickupRequest/t:PickupNo/t:string') === 'PU-555');
});

it('calls any operation through call()', function (): void {
    TestCase::fakeAmbro(['GetServices' => TestCase::asmx('GetServices', '<Services />')]);

    expect(Ambro::call('GetServices', [], true))->toMatchArray(['responseCode' => '0']);
});
