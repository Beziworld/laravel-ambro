# Laravel Ambro Express

[![tests](https://github.com/beziworld/laravel-ambro/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-ambro/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-ambro.svg)](https://packagist.org/packages/beziworld/laravel-ambro)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-ambro.svg)](LICENSE.md)

[Ambro Express](https://ambroexpress.pl) client for Laravel, built on the official SOAP web service (`api.asmx`, documentation v1.13):

- shipments of packages, long packages, Size20, pallets and furniture through a typed, validating builder
- cash on delivery, insurance, currency and additional services (SMS, EMAIL, ROD, carry in, assembly, unpacking, take away, carry down)
- labels (PDF for a label printer or A4), waybills and handover manifests
- courier pickups: available windows, ordering and cancellation
- tracking with multilingual status descriptions
- services enabled for the account and services available for a route
- multiple accounts (named connections and runtime credentials), `php artisan ambro:services`
- no `ext-soap`: requests go through the Laravel HTTP client, so `Http::fake()` works in your tests

[Polska wersja dokumentacji](README.pl.md)

## Why this package

Packagist has no Ambro Express client (searches for `ambro` and `ambro express` return only unrelated packages), and the
existing integrations live inside SaaS platforms such as BaseLinker or Comarch Shipping. This package covers all 13
operations of the WSDL, validates the rules from the official error list before a request is sent, and never retries
operations that create or cancel something.

## Installation

```bash
composer require beziworld/laravel-ambro
php artisan vendor:publish --tag=ambro-config   # optional
```

```dotenv
AMBRO_USERNAME=...              # API login issued by Ambro Express
AMBRO_PASSWORD=...
AMBRO_SANDBOX=false             # true = dev.api.ambro.opennet.pl (documented test login: demo / demo)
AMBRO_SERVICE=38                # 38 Standard, 200 MEBEL
AMBRO_LABEL_FORMAT=PDF          # PDF or PDFA4
AMBRO_CURRENCY=                 # optional currency of COD and insurance, e.g. PLN or EUR

AMBRO_SENDER_NAME="ACME Sp. z o.o."
AMBRO_SENDER_ADDRESS="ul. Prosta 1"
AMBRO_SENDER_CITY=Warszawa
AMBRO_SENDER_POST_CODE=00-001
AMBRO_SENDER_PERSON="Jan Nowak"
AMBRO_SENDER_PHONE=500600700
AMBRO_SENDER_EMAIL=magazyn@example.com
```

The API requires the name, address, city, postal code, country, contact person, phone and e-mail for both sides.

## Send a shipment

```php
use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Enums\AdditionalService;
use Beziworld\Ambro\Facades\Ambro;

$shipment = Ambro::shipment()                       // sender, service and label format from the config
    ->to(new Location(
        name: 'Anna Kowalska',
        address: 'ul. Długa 5/3',
        city: 'Kraków',
        postCode: '30-001',
        person: 'Anna Kowalska',
        phone: '600700800',
        email: 'anna@example.com',
        privatePerson: true,
    ))
    ->package(12.5, length: 60, height: 40, width: 30, content: 'Books')
    ->package(4)
    ->cashOnDelivery('149.99', 'PL61 1090 1014 0000 0712 1981 2874')
    ->insurance(500)
    ->with(AdditionalService::Sms, AdditionalService::Email)
    ->reference('ORDER-1')
    ->send();

$shipment->packageNumber;        // main package number
$shipment->labels[0]->content;   // PDF binary, one label per parcel
Storage::put($shipment->labels[0]->filename(), $shipment->labels[0]->content);
```

Pallets and furniture:

```php
use Beziworld\Ambro\Enums\LabelFormat;
use Beziworld\Ambro\Enums\Service;

Ambro::shipment()
    ->to($recipient)
    ->service(Service::Furniture)
    ->palette(180, length: 120, height: 150, width: 80)
    ->with(AdditionalService::CarryIn, AdditionalService::Assembly)
    ->labelFormat(LabelFormat::PdfA4)
    ->send();
```

`build()` returns the validated `Shipment` without sending it; pass it to `Ambro::createShipment()` later. Validation
covers the documented limits: Package and LongPackage up to 50 kg, Palette up to 200 kg, Size20 up to 20 kg, pallets
not mixed with other parcels, insurance at least the COD amount, no duplicated additional services and Poland on one side.

Which services can carry a shipment (the API has no price operation):

```php
Ambro::availableServices(Ambro::shipment()->to($recipient)->palette(150), readyAt: now()->addDay());
Ambro::servicesInfo();   // services with their additional services; also: php artisan ambro:services
```

## Labels, waybills and manifests

```php
Ambro::labels(['AMB0001234567', 'AMB0001234568']);   // list<Label>, one per parcel
Ambro::label('AMB0001234567');                       // single label document (GetStrictLabel)
Ambro::waybills('AMB0001234567');
Ambro::manifest(['AMB0001234567', 'AMB0001234568']); // handover manifest for the courier
Ambro::cancelShipments('AMB0001234567');
```

## Courier pickup

```php
use Beziworld\Ambro\Data\Pickup;

$windows = Ambro::availablePickups(packageNumbers: ['AMB0001234567']);   // up to 3 nearest days
$window = $windows[0];   // earliestReadyAt, latestPickupAt, minimumInterval (minutes)

$pickupNumber = Ambro::callPickup(new Pickup(
    location: Ambro::config()->sender,
    readyAt: $window->earliestReadyAt,
    maxPickupAt: $window->latestPickupAt,
    packageNumbers: ['AMB0001234567'],
));

Ambro::cancelPickups($pickupNumber);
```

Times are sent in Polish local time (Europe/Warsaw), whatever the time zone of the passed dates.

## Tracking

```php
$tracking = Ambro::tracking('AMB0001234567');

$tracking->current?->code;             // e.g. PKL, PDD, AWI, DOR
$tracking->isDelivered();
$tracking->pickedUpAt;                 // CarbonImmutable, Europe/Warsaw

foreach ($tracking->events as $event) {
    $event->occurredAt;
    $event->description('en');         // PL, EN, DE, CZ, SK, HU, RO
    $event->parameter;                 // e.g. the city of the event
}
```

## Multiple accounts

```php
// config/ambro.php
'connections' => [
    'default' => [],
    'warehouse' => [
        'username' => env('AMBRO_WAREHOUSE_USERNAME'),
        'password' => env('AMBRO_WAREHOUSE_PASSWORD'),
        'sender' => ['city' => 'Poznań', 'address' => 'ul. Składowa 3', 'post_code' => '61-001'],
    ],
],
```

```php
Ambro::connection('warehouse')->tracking('AMB0001234567');
Ambro::using(['username' => $tenant->ambro_login, 'password' => $tenant->ambro_password])->services();
```

Connection options are merged over the shared ones, so a connection only lists what differs.

## Errors

| Exception | When |
| --- | --- |
| `InvalidShipment` | A documented rule is broken; nothing is sent. |
| `ApiException` | `responseCode` is not 0 (`errorCode()`, `description()`), a SOAP fault or an unexpected HTTP status (`httpStatus()`). |
| `AuthenticationFailed` | Response codes 401, 2 or 3, or HTTP 401. |
| `ConnectionFailed` | The service cannot be reached. Reads are retried first (`AMBRO_READ_RETRIES`, default 2). |
| `InvalidConfiguration` | Missing credentials or an unknown connection. |

All exceptions extend `AmbroException`. `createShipment()`, `callPickup()` and cancellations are never retried: after a
timeout check the Ambro panel before sending again. Any operation can also be called directly with
`Ambro::call('GetServices', [], read: true)`.

## Notes

- The official documentation lists plain HTTP endpoints (`http://api.ambro.opennet.pl/api.asmx`); override them with
  `endpoint` in a connection if Ambro gives you another address.
- The documentation describes `COD.RetAccountNo` as an account number while the WSDL declares a decimal; the package
  sends the digits of the account as text.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues: [GitHub issues](https://github.com/beziworld/laravel-ambro/issues)

Need an integration? Get in touch through [beziworld.eu](https://beziworld.eu).

### Other packages by the author

[laravel-geis](https://github.com/beziworld/laravel-geis) ·
[laravel-dpd](https://github.com/beziworld/laravel-dpd) ·
[laravel-poczta-polska](https://github.com/beziworld/laravel-poczta-polska) ·
[laravel-inpost](https://github.com/beziworld/laravel-inpost) ·
[laravel-gls](https://github.com/beziworld/laravel-gls)

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with Ambro Express or OpenNet.
