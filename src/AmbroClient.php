<?php

declare(strict_types=1);

namespace Beziworld\Ambro;

use Beziworld\Ambro\Data\CreatedShipment;
use Beziworld\Ambro\Data\Label;
use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Data\Manifest;
use Beziworld\Ambro\Data\Parcel;
use Beziworld\Ambro\Data\Pickup;
use Beziworld\Ambro\Data\PickupWindow;
use Beziworld\Ambro\Data\ServiceInfo;
use Beziworld\Ambro\Data\Shipment;
use Beziworld\Ambro\Data\Tracking;
use Beziworld\Ambro\Exceptions\ApiException;
use Beziworld\Ambro\Exceptions\AuthenticationFailed;
use Beziworld\Ambro\Exceptions\ConnectionFailed;
use Beziworld\Ambro\Exceptions\InvalidShipment;
use Beziworld\Ambro\Support\Soap;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Client of the Ambro Express SOAP web service (api.asmx) for one account.
 *
 * Every operation sends the AuthToken of the account and checks the
 * responseCode of the result (0 means success). Read operations are
 * retried on connection errors and HTTP 502, 503 and 504; operations that
 * create or cancel something are never retried.
 */
final class AmbroClient
{
    /** @var list<int> */
    private const array RETRYABLE_STATUSES = [502, 503, 504];

    /** @var list<int> */
    private const array AUTHENTICATION_CODES = [2, 3, 401];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly AmbroConfig $config,
        private readonly ?string $connection = null,
    ) {}

    public function config(): AmbroConfig
    {
        return $this->config;
    }

    /**
     * Name of the configured connection; null for a client created with using().
     */
    public function connectionName(): ?string
    {
        return $this->connection;
    }

    /**
     * Starts a shipment with the sender, service, currency and label format of this connection.
     */
    public function shipment(): ShipmentBuilder
    {
        return new ShipmentBuilder($this, $this->config->service, $this->config->labelFormat, $this->config->currency, $this->config->sender);
    }

    /**
     * Registers a shipment and returns its package number with the labels (CreateShipment).
     *
     * The call is never retried: a timeout may still have created the shipment.
     *
     * @throws InvalidShipment When a documented rule is broken.
     */
    public function createShipment(Shipment|ShipmentBuilder $shipment): CreatedShipment
    {
        $shipment = $shipment instanceof ShipmentBuilder ? $shipment->build() : $shipment;
        $shipment->validate();

        return CreatedShipment::fromSoap($this->call('CreateShipment', ['ShipmentRequest' => $shipment->toSoap()]));
    }

    /**
     * Cancels shipments that have not been handed over yet (CancelShipment).
     *
     * @param string|list<string> $packageNumbers
     */
    public function cancelShipments(string|array $packageNumbers): void
    {
        $this->call('CancelShipment', ['cancelShipmentRequest' => ['PackageNo' => Soap::arrayOfString(self::numbers($packageNumbers))]]);
    }

    /**
     * Labels of previously created shipments, one document per parcel (GetLabel).
     *
     * @param string|list<string> $packageNumbers
     * @return list<Label>
     */
    public function labels(string|array $packageNumbers): array
    {
        $result = $this->call('GetLabel', ['request' => ['PackageNo' => Soap::arrayOfString(self::numbers($packageNumbers))]], true);

        return array_map(Label::fromSoap(...), Soap::items($result['LabelData'] ?? null, 'Label'));
    }

    /**
     * Single label document of a previously created shipment (GetStrictLabel).
     */
    public function label(string $packageNumber): Label
    {
        $result = $this->call('GetStrictLabel', ['request' => ['PackageNo' => Soap::arrayOfString(self::numbers($packageNumber))]], true);
        $labels = Soap::items($result['LabelData'] ?? null, 'Label');

        if ($labels === []) {
            throw ApiException::fault('GetStrictLabel', 'the response contains no label.', null);
        }

        return Label::fromSoap($labels[0]);
    }

    /**
     * Waybills (consignment notes) of the given shipments (GetWaybill).
     *
     * @param string|list<string> $packageNumbers
     * @return list<Label>
     */
    public function waybills(string|array $packageNumbers): array
    {
        $result = $this->call('GetWaybill', ['request' => ['PackageNo' => Soap::arrayOfString(self::numbers($packageNumbers))]], true);

        return array_map(Label::fromSoap(...), Soap::items($result['WaybillData'] ?? null, 'Waybill'));
    }

    /**
     * Handover manifest for the given shipments (GetManifest).
     *
     * @param string|list<string> $packageNumbers
     */
    public function manifest(string|array $packageNumbers): Manifest
    {
        return Manifest::fromSoap($this->call('GetManifest', ['request' => ['PackageNo' => Soap::arrayOfString(self::numbers($packageNumbers))]], true));
    }

    /**
     * Tracking history of a package (GetTracking).
     */
    public function tracking(string $packageNumber): Tracking
    {
        $packageNumber = trim($packageNumber);

        return Tracking::fromSoap($packageNumber, $this->call('GetTracking', ['packageNo' => $packageNumber], true));
    }

    /**
     * Services handled by the API for the account (GetServices).
     *
     * @return list<ServiceInfo>
     */
    public function services(): array
    {
        $result = $this->call('GetServices', [], true);

        return array_map(ServiceInfo::fromSoap(...), Soap::items($result['Services'] ?? null, 'Service'));
    }

    /**
     * Services with their additional services (GetServicesInfo).
     *
     * @return list<ServiceInfo>
     */
    public function servicesInfo(): array
    {
        $result = $this->call('GetServicesInfo', [], true);

        return array_map(ServiceInfo::fromSoap(...), Soap::items($result['Services'] ?? null, 'ServiceInfo'));
    }

    /**
     * Services available for a route, parcels and options on a given ready date (GetAvailableServices).
     *
     * The service id of the shipment is ignored; the API returns every
     * service that can carry it. Prices are not part of the response.
     *
     * @return list<ServiceInfo>
     */
    public function availableServices(Shipment|ShipmentBuilder $shipment, ?DateTimeInterface $readyAt = null): array
    {
        $shipment = $shipment instanceof ShipmentBuilder ? $shipment->build() : $shipment;
        $result = $this->call('GetAvailableServices', ['getAvailableServicesRequest' => $shipment->toAvailabilityRequest($readyAt ?? now())], true);

        return array_map(ServiceInfo::fromSoap(...), Soap::items($result['AvailableServices'] ?? null, 'Service'));
    }

    /**
     * Up to three nearest days on which a courier can collect the shipments (GetAvailablePickups).
     *
     * @param list<string> $packageNumbers
     * @param list<Parcel> $parcels
     * @return list<PickupWindow>
     */
    public function availablePickups(?Location $location = null, array $packageNumbers = [], array $parcels = []): array
    {
        $location ??= $this->config->sender ?? throw InvalidShipment::because('the pickup location is not set; pass it or configure ambro.sender.');

        $result = $this->call('GetAvailablePickups', ['getAvailablePickupsRequest' => [
            'PickupLocation' => $location->toSoap(),
            'PackageNo' => $packageNumbers !== [] ? Soap::arrayOfString($packageNumbers) : null,
            'Parcels' => $parcels !== [] ? ['Parcel' => array_map(static fn(Parcel $parcel): array => $parcel->toSoap(), $parcels)] : null,
        ]], true);

        return array_map(PickupWindow::fromSoap(...), Soap::items($result['AvailablePickupDays'] ?? null, 'AvailablePickupDay'));
    }

    /**
     * Orders a courier pickup and returns its number (CallPickup).
     *
     * @throws InvalidShipment When a documented rule is broken.
     */
    public function callPickup(Pickup $pickup): string
    {
        $pickup->validate();
        $result = $this->call('CallPickup', ['callPickupRequest' => $pickup->toSoap()]);

        return Soap::string($result, 'PickupNo') ?? throw ApiException::fault('CallPickup', 'the response contains no pickup number.', null);
    }

    /**
     * Cancels courier pickups (CancelPickup).
     *
     * @param string|list<string> $pickupNumbers
     */
    public function cancelPickups(string|array $pickupNumbers): void
    {
        $this->call('CancelPickup', ['cancelPickupRequest' => ['PickupNo' => Soap::arrayOfString(self::numbers($pickupNumbers))]]);
    }

    /**
     * Calls any operation with the AuthToken of the account and returns its {Operation}Result element.
     *
     * @param array<string, mixed> $parameters Operation parameters after the token, in WSDL order.
     * @param bool $read Whether the operation only reads data and may be retried.
     * @return array<array-key, mixed>
     *
     * @throws ApiException When responseCode is not 0, on a SOAP fault or an unexpected HTTP status.
     * @throws AuthenticationFailed When the credentials are rejected.
     * @throws ConnectionFailed When the service cannot be reached.
     */
    public function call(string $operation, array $parameters = [], bool $read = false): array
    {
        $envelope = Soap::envelope(AmbroConfig::NAMESPACE, $operation, [
            'token' => ['UserName' => $this->config->username, 'Password' => $this->config->password],
        ] + $parameters);

        $response = $this->send($operation, $envelope, $read ? $this->config->readRetries : 0);

        if ($response->status() === 401) {
            throw AuthenticationFailed::for($operation, 401);
        }

        $parsed = Soap::parse($response->body());

        if ($parsed['fault'] !== null) {
            throw ApiException::fault($operation, $parsed['fault']['message'], $response->status());
        }

        if (! $response->successful()) {
            throw ApiException::fault($operation, sprintf('HTTP status %d', $response->status()), $response->status());
        }

        $result = $parsed['body'][$operation . 'Result'] ?? null;
        $result = is_array($result) ? $result : [];
        $code = Soap::int($result, 'responseCode') ?? 0;

        if (in_array($code, self::AUTHENTICATION_CODES, true)) {
            throw AuthenticationFailed::for($operation, $code);
        }

        if ($code !== 0) {
            throw ApiException::response($operation, $code, Soap::string($result, 'responseDescription'));
        }

        return $result;
    }

    private function send(string $operation, string $envelope, int $retries): Response
    {
        $attempt = 0;

        while (true) {
            try {
                $response = $this->http->createPendingRequest()
                    ->timeout($this->config->timeout)
                    ->withHeaders(['SOAPAction' => '"' . AmbroConfig::NAMESPACE . $operation . '"'])
                    ->withBody($envelope, 'text/xml; charset=utf-8')
                    ->post($this->config->endpoint());
            } catch (ConnectionException $exception) {
                if ($attempt++ < $retries) {
                    continue;
                }

                throw ConnectionFailed::during($operation, $exception);
            }

            if (in_array($response->status(), self::RETRYABLE_STATUSES, true) && $attempt++ < $retries) {
                continue;
            }

            return $response;
        }
    }

    /**
     * @param string|list<string> $numbers
     * @return list<string>
     */
    private static function numbers(string|array $numbers): array
    {
        $numbers = array_values(array_filter(array_map(trim(...), is_array($numbers) ? $numbers : [$numbers]), static fn(string $number): bool => $number !== ''));

        if ($numbers === []) {
            throw InvalidShipment::because('at least one number is required.');
        }

        return $numbers;
    }
}
