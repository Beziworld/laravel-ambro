<?php

declare(strict_types=1);

use Beziworld\Ambro\Exceptions\ApiException;
use Beziworld\Ambro\Exceptions\ConnectionFailed;
use Beziworld\Ambro\Exceptions\InvalidShipment;
use Beziworld\Ambro\Facades\Ambro;
use Beziworld\Ambro\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('downloads labels for several packages', function (): void {
    TestCase::fakeAmbro(['GetLabel' => TestCase::asmx('GetLabel', '<LabelData><Label><ParcelID>P1</ParcelID><MimeFormat>PDF</MimeFormat><MimeData>' . base64_encode('pdf-1') . '</MimeData></Label>'
        . '<Label><ParcelID>P2</ParcelID><MimeFormat>PDF</MimeFormat><MimeData>' . base64_encode('pdf-2') . '</MimeData></Label></LabelData>')]);

    $labels = Ambro::labels(['AMB1', ' AMB2 ']);

    expect($labels)->toHaveCount(2)
        ->and($labels[1]->parcelId)->toBe('P2')
        ->and($labels[1]->content)->toBe('pdf-2')
        ->and($labels[1]->base64())->toBe(base64_encode('pdf-2'))
        ->and($labels[1]->mimeType())->toBe('application/pdf');

    Http::assertSent(fn(Request $request): bool => TestCase::nodes($request, '//t:GetLabel/t:request/t:PackageNo/t:string') === 2
        && TestCase::at($request, '//t:GetLabel/t:request/t:PackageNo/t:string[2]') === 'AMB2');
});

it('downloads a single label', function (): void {
    TestCase::fakeAmbro(['GetStrictLabel' => TestCase::asmx('GetStrictLabel', '<LabelData><Label><ParcelID>AMB1</ParcelID><MimeFormat>PDFA4</MimeFormat><MimeData>' . base64_encode('a4') . '</MimeData></Label></LabelData>')]);

    $label = Ambro::label('AMB1');

    expect($label->format)->toBe('PDFA4')->and($label->content)->toBe('a4');
});

it('fails when the single label response is empty', function (): void {
    TestCase::fakeAmbro(['GetStrictLabel' => TestCase::asmx('GetStrictLabel', '<LabelData />')]);

    expect(fn() => Ambro::label('AMB1'))->toThrow(ApiException::class, 'no label');
});

it('downloads waybills', function (): void {
    TestCase::fakeAmbro(['GetWaybill' => TestCase::asmx('GetWaybill', '<WaybillData><Waybill><ParcelID>AMB1</ParcelID><MimeFormat>PDF</MimeFormat><MimeData>' . base64_encode('waybill') . '</MimeData></Waybill></WaybillData>')]);

    $waybills = Ambro::waybills('AMB1');

    expect($waybills)->toHaveCount(1)->and($waybills[0]->content)->toBe('waybill');
});

it('downloads a manifest', function (): void {
    TestCase::fakeAmbro(['GetManifest' => TestCase::asmx('GetManifest', '<ManifestNo>M-77</ManifestNo><MimeFormat>PDF</MimeFormat><MimeData>' . base64_encode('manifest') . '</MimeData>')]);

    $manifest = Ambro::manifest(['AMB1', 'AMB2']);

    expect($manifest->number)->toBe('M-77')
        ->and($manifest->content)->toBe('manifest')
        ->and($manifest->raw)->not->toHaveKey('MimeData');
});

it('requires at least one package number', function (): void {
    Http::fake();

    expect(fn() => Ambro::labels([' ']))->toThrow(InvalidShipment::class);
    Http::assertNothingSent();
});

it('cancels shipments', function (): void {
    TestCase::fakeAmbro(['CancelShipment' => TestCase::asmx('CancelShipment')]);

    Ambro::cancelShipments(['AMB1', 'AMB2']);

    Http::assertSent(fn(Request $request): bool => TestCase::operation($request) === 'CancelShipment'
        && TestCase::at($request, '//t:cancelShipmentRequest/t:PackageNo/t:string[1]') === 'AMB1');
});

it('reports a refused cancellation', function (): void {
    TestCase::fakeAmbro(['CancelShipment' => TestCase::asmx('CancelShipment', '', 1035, 'Carrier denied to cancel package(s)')]);

    expect(fn() => Ambro::cancelShipments('AMB1'))->toThrow(ApiException::class, 'Carrier denied');
});

it('returns the tracking history in chronological order', function (): void {
    TestCase::fakeAmbro(['GetTracking' => TestCase::asmx('GetTracking', '<Status>'
        . '<OrderStatus><PackageNo>AMB1</PackageNo><Code>DOR</Code><Description>Doręczono</Description><DescriptionEN>Delivered</DescriptionEN><EventTimestamp>2026-11-05T13:40:00</EventTimestamp><EventParam>Kraków</EventParam></OrderStatus>'
        . '<OrderStatus><PackageNo>AMB1</PackageNo><Code>PKL</Code><Description>Przyjęta od Klienta przez Kuriera</Description><EventTimestamp>2026-11-04T15:10:00</EventTimestamp></OrderStatus>'
        . '</Status><DatePicked>2026-11-04T15:10:00</DatePicked><DateDelivered xsi:nil="true" />'
        . '<CurrentStatus><PackageNo>AMB1</PackageNo><Code>DOR</Code><Description>Doręczono</Description><EventTimestamp>2026-11-05T13:40:00</EventTimestamp></CurrentStatus>')]);

    $tracking = Ambro::tracking(' AMB1 ');

    expect($tracking->packageNumber)->toBe('AMB1')
        ->and($tracking->events)->toHaveCount(2)
        ->and($tracking->events[0]->code)->toBe('PKL')
        ->and($tracking->events[1]->description('en'))->toBe('Delivered')
        ->and($tracking->events[1]->description('de'))->toBe('Doręczono')
        ->and($tracking->events[1]->parameter)->toBe('Kraków')
        ->and($tracking->events[1]->occurredAt?->format('Y-m-d H:i T'))->toBe('2026-11-05 13:40 CET')
        ->and($tracking->pickedUpAt?->toDateString())->toBe('2026-11-04')
        ->and($tracking->deliveredAt)->toBeNull()
        ->and($tracking->current?->code)->toBe('DOR')
        ->and($tracking->isDelivered())->toBeTrue();

    Http::assertSent(fn(Request $request): bool => TestCase::at($request, '//t:GetTracking/t:packageNo') === 'AMB1');
});

it('falls back to the latest event when no current status is returned', function (): void {
    TestCase::fakeAmbro(['GetTracking' => TestCase::asmx('GetTracking', '<Status><OrderStatus><Code>ZWR</Code><EventTimestamp>2026-11-06T08:00:00</EventTimestamp></OrderStatus></Status><DatePicked xsi:nil="true" /><DateDelivered xsi:nil="true" />')]);

    $tracking = Ambro::tracking('AMB1');

    expect($tracking->current?->isReturned())->toBeTrue()
        ->and($tracking->isDelivered())->toBeFalse();
});

it('reports unknown packages', function (): void {
    TestCase::fakeAmbro(['GetTracking' => TestCase::asmx('GetTracking', '', 1109, 'Tracking data is not available for given package number')]);

    expect(fn() => Ambro::tracking('NOPE'))->toThrow(ApiException::class, '1109');
});

it('retries read operations after a connection error', function (): void {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        if ($attempts++ === 0) {
            throw new ConnectionException('reset');
        }

        return Http::response(TestCase::asmx('GetTracking', '<Status />'), 200);
    });

    expect(Ambro::tracking('AMB1')->events)->toBe([])
        ->and($attempts)->toBe(2);
});

it('retries read operations on HTTP 503 and gives up after the configured attempts', function (): void {
    Http::fakeSequence()->push('busy', 503)->push('busy', 503)->push('busy', 503);

    expect(fn() => Ambro::tracking('AMB1'))->toThrow(ApiException::class);
    Http::assertSentCount(2);
});

it('fails with ConnectionFailed when reads keep failing', function (): void {
    Http::fake(fn() => throw new ConnectionException('down'));

    expect(fn() => Ambro::services())->toThrow(ConnectionFailed::class, 'GetServices');
});
