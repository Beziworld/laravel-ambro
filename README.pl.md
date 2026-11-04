# Laravel Ambro Express

[![tests](https://github.com/beziworld/laravel-ambro/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-ambro/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-ambro.svg)](https://packagist.org/packages/beziworld/laravel-ambro)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-ambro.svg)](LICENSE.md)

Klient [Ambro Express](https://ambroexpress.pl) dla Laravela, oparty na oficjalnym web service SOAP (`api.asmx`, dokumentacja v1.13):

- przesyłki: paczki, dłużyce, gabaryt 20, palety i meble, przez typowany builder z walidacją
- pobranie, ubezpieczenie, waluta i usługi dodatkowe (SMS, EMAIL, ROD, wniesienie, montaż, rozpakowanie, zabranie, zniesienie)
- etykiety (PDF na drukarkę etykiet lub A4), listy przewozowe i manifesty nadania
- podjazdy kuriera: dostępne okna, zamawianie i anulowanie
- śledzenie z opisami statusów w wielu językach
- serwisy dostępne na koncie i serwisy dostępne dla trasy
- wiele kont (nazwane połączenia i dane dostępowe w runtime), `php artisan ambro:services`
- bez `ext-soap`: żądania idą przez klienta HTTP Laravela, więc `Http::fake()` działa w testach

[English documentation](README.md)

## Dlaczego ten pakiet

Na Packagist nie ma klienta Ambro Express (wyszukiwania `ambro` i `ambro express` zwracają wyłącznie niezwiązane
pakiety), a istniejące integracje działają wewnątrz platform SaaS, takich jak BaseLinker czy Comarch Shipping. Pakiet
obsługuje wszystkie 13 operacji z WSDL, sprawdza reguły z oficjalnej listy błędów przed wysłaniem żądania i nigdy nie
ponawia operacji, które coś tworzą lub anulują.

## Instalacja

```bash
composer require beziworld/laravel-ambro
php artisan vendor:publish --tag=ambro-config   # opcjonalnie
```

```dotenv
AMBRO_USERNAME=...              # login API nadany przez Ambro Express
AMBRO_PASSWORD=...
AMBRO_SANDBOX=false             # true = dev.api.ambro.opennet.pl (testowy login z dokumentacji: demo / demo)
AMBRO_SERVICE=38                # 38 Standard, 200 MEBEL
AMBRO_LABEL_FORMAT=PDF          # PDF lub PDFA4
AMBRO_CURRENCY=                 # opcjonalna waluta pobrania i ubezpieczenia, np. PLN lub EUR

AMBRO_SENDER_NAME="ACME Sp. z o.o."
AMBRO_SENDER_ADDRESS="ul. Prosta 1"
AMBRO_SENDER_CITY=Warszawa
AMBRO_SENDER_POST_CODE=00-001
AMBRO_SENDER_PERSON="Jan Nowak"
AMBRO_SENDER_PHONE=500600700
AMBRO_SENDER_EMAIL=magazyn@example.com
```

API wymaga nazwy, adresu, miasta, kodu pocztowego, kraju, osoby kontaktowej, telefonu i e-maila po obu stronach.

## Nadanie przesyłki

```php
use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Enums\AdditionalService;
use Beziworld\Ambro\Facades\Ambro;

$shipment = Ambro::shipment()                       // nadawca, serwis i format etykiety z konfiguracji
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
    ->package(12.5, length: 60, height: 40, width: 30, content: 'Książki')
    ->package(4)
    ->cashOnDelivery('149.99', 'PL61 1090 1014 0000 0712 1981 2874')
    ->insurance(500)
    ->with(AdditionalService::Sms, AdditionalService::Email)
    ->reference('ZAM-1')
    ->send();

$shipment->packageNumber;        // główny numer przesyłki
$shipment->labels[0]->content;   // PDF, jedna etykieta na paczkę
Storage::put($shipment->labels[0]->filename(), $shipment->labels[0]->content);
```

Palety i meble:

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

`build()` zwraca zwalidowany obiekt `Shipment` bez wysyłania; można go później przekazać do `Ambro::createShipment()`.
Walidacja obejmuje udokumentowane limity: Package i LongPackage do 50 kg, Palette do 200 kg, Size20 do 20 kg, palet nie
łączy się z innymi paczkami, ubezpieczenie co najmniej równe pobraniu, bez powtórzonych usług dodatkowych i Polska po
jednej stronie.

Jakie serwisy mogą przewieźć przesyłkę (API nie ma operacji wyceny):

```php
Ambro::availableServices(Ambro::shipment()->to($recipient)->palette(150), readyAt: now()->addDay());
Ambro::servicesInfo();   // serwisy z usługami dodatkowymi; także: php artisan ambro:services
```

## Etykiety, listy przewozowe i manifesty

```php
Ambro::labels(['AMB0001234567', 'AMB0001234568']);   // list<Label>, po jednej na paczkę
Ambro::label('AMB0001234567');                       // pojedynczy dokument etykiety (GetStrictLabel)
Ambro::waybills('AMB0001234567');
Ambro::manifest(['AMB0001234567', 'AMB0001234568']); // manifest nadania dla kuriera
Ambro::cancelShipments('AMB0001234567');
```

## Podjazd kuriera

```php
use Beziworld\Ambro\Data\Pickup;

$windows = Ambro::availablePickups(packageNumbers: ['AMB0001234567']);   // do 3 najbliższych dni
$window = $windows[0];   // earliestReadyAt, latestPickupAt, minimumInterval (minuty)

$pickupNumber = Ambro::callPickup(new Pickup(
    location: Ambro::config()->sender,
    readyAt: $window->earliestReadyAt,
    maxPickupAt: $window->latestPickupAt,
    packageNumbers: ['AMB0001234567'],
));

Ambro::cancelPickups($pickupNumber);
```

Godziny są wysyłane w polskim czasie lokalnym (Europe/Warsaw), niezależnie od strefy przekazanych dat.

## Śledzenie

```php
$tracking = Ambro::tracking('AMB0001234567');

$tracking->current?->code;             // np. PKL, PDD, AWI, DOR
$tracking->isDelivered();
$tracking->pickedUpAt;                 // CarbonImmutable, Europe/Warsaw

foreach ($tracking->events as $event) {
    $event->occurredAt;
    $event->description('en');         // PL, EN, DE, CZ, SK, HU, RO
    $event->parameter;                 // np. miasto zdarzenia
}
```

## Wiele kont

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

Opcje połączenia nakładają się na wspólne, więc połączenie zawiera tylko to, co się różni.

## Błędy

| Wyjątek | Kiedy |
| --- | --- |
| `InvalidShipment` | Naruszona udokumentowana reguła; nic nie jest wysyłane. |
| `ApiException` | `responseCode` różny od 0 (`errorCode()`, `description()`), SOAP fault lub nieoczekiwany status HTTP (`httpStatus()`). |
| `AuthenticationFailed` | Kody odpowiedzi 401, 2 lub 3 albo HTTP 401. |
| `ConnectionFailed` | Brak połączenia z usługą. Odczyty są najpierw ponawiane (`AMBRO_READ_RETRIES`, domyślnie 2). |
| `InvalidConfiguration` | Brak danych dostępowych lub nieznane połączenie. |

Wszystkie wyjątki dziedziczą po `AmbroException`. `createShipment()`, `callPickup()` i anulowania nigdy nie są
ponawiane: po timeoucie sprawdź panel Ambro, zanim wyślesz ponownie. Dowolną operację można też wywołać bezpośrednio:
`Ambro::call('GetServices', [], read: true)`.

## Uwagi

- Oficjalna dokumentacja podaje adresy HTTP (`http://api.ambro.opennet.pl/api.asmx`); jeśli Ambro przekaże inny adres,
  ustaw `endpoint` w połączeniu.
- Dokumentacja opisuje `COD.RetAccountNo` jako numer konta, a WSDL deklaruje typ decimal; pakiet wysyła cyfry numeru
  konta jako tekst.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Autor

Pakiet tworzy i utrzymuje **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Zgłoszenia: [GitHub issues](https://github.com/beziworld/laravel-ambro/issues)

Potrzebujesz integracji? Skontaktuj się przez [beziworld.eu](https://beziworld.eu).

### Inne pakiety autora

[laravel-geis](https://github.com/beziworld/laravel-geis) ·
[laravel-dpd](https://github.com/beziworld/laravel-dpd) ·
[laravel-poczta-polska](https://github.com/beziworld/laravel-poczta-polska) ·
[laravel-inpost](https://github.com/beziworld/laravel-inpost) ·
[laravel-gls](https://github.com/beziworld/laravel-gls)

## Licencja

MIT. Zobacz [LICENSE.md](LICENSE.md). Pakiet nie jest powiązany z Ambro Express ani OpenNet.
