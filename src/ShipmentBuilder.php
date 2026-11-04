<?php

declare(strict_types=1);

namespace Beziworld\Ambro;

use Beziworld\Ambro\Data\CashOnDelivery;
use Beziworld\Ambro\Data\CreatedShipment;
use Beziworld\Ambro\Data\Location;
use Beziworld\Ambro\Data\Parcel;
use Beziworld\Ambro\Data\Shipment;
use Beziworld\Ambro\Enums\AdditionalService;
use Beziworld\Ambro\Enums\LabelFormat;
use Beziworld\Ambro\Enums\ParcelType;
use Beziworld\Ambro\Enums\Service;
use Beziworld\Ambro\Exceptions\InvalidShipment;
use Beziworld\Ambro\Support\Values;

/**
 * Fluent builder of a Shipment.
 *
 * Obtained from AmbroClient::shipment(), it starts with the sender, the
 * service, the currency and the label format of the connection. build()
 * validates the documented rules; send() also registers the shipment.
 */
final class ShipmentBuilder
{
    private ?Location $from;

    private ?Location $to = null;

    /** @var list<Parcel> */
    private array $parcels = [];

    private ?CashOnDelivery $cashOnDelivery = null;

    private ?string $insurance = null;

    /** @var list<string> */
    private array $additionalServices = [];

    private ?string $content = null;

    private ?string $costCenter = null;

    private ?string $reference = null;

    public function __construct(
        private readonly ?AmbroClient $client = null,
        private int $service = 38,
        private LabelFormat $labelFormat = LabelFormat::Pdf,
        private ?string $currency = null,
        ?Location $from = null,
    ) {
        $this->from = $from;
    }

    public function from(Location $sender): self
    {
        $this->from = $sender;

        return $this;
    }

    public function to(Location $recipient): self
    {
        $this->to = $recipient;

        return $this;
    }

    public function service(Service|int $service): self
    {
        $this->service = $service instanceof Service ? $service->value : $service;

        return $this;
    }

    public function parcel(Parcel ...$parcels): self
    {
        foreach ($parcels as $parcel) {
            $this->parcels[] = $parcel;
        }

        return $this;
    }

    /**
     * Adds a package of up to 50 kg; dimensions in centimetres.
     */
    public function package(float $weight, ?float $length = null, ?float $height = null, ?float $width = null, ?string $content = null, ?string $reference = null): self
    {
        return $this->parcel(new Parcel($weight, ParcelType::Package, $length, $height, $width, $content, $reference));
    }

    /**
     * Adds a pallet of up to 200 kg; pallets cannot be mixed with other parcel types.
     */
    public function palette(float $weight, ?float $length = null, ?float $height = null, ?float $width = null, ?string $content = null, ?string $reference = null): self
    {
        return $this->parcel(new Parcel($weight, ParcelType::Palette, $length, $height, $width, $content, $reference));
    }

    public function furniture(float $weight, ?float $length = null, ?float $height = null, ?float $width = null, ?string $content = null, ?string $reference = null): self
    {
        return $this->parcel(new Parcel($weight, ParcelType::Furniture, $length, $height, $width, $content, $reference));
    }

    public function cashOnDelivery(string|int|float $amount, string $accountNumber): self
    {
        $this->cashOnDelivery = new CashOnDelivery($amount, $accountNumber);

        return $this;
    }

    /**
     * Declared value; it must be at least the COD amount.
     */
    public function insurance(string|int|float $amount): self
    {
        $this->insurance = Values::decimal($amount);

        return $this;
    }

    public function currency(string $code): self
    {
        $this->currency = strtoupper($code);

        return $this;
    }

    public function with(AdditionalService|string ...$services): self
    {
        foreach ($services as $service) {
            $this->additionalServices[] = $service instanceof AdditionalService ? $service->value : strtoupper($service);
        }

        return $this;
    }

    public function labelFormat(LabelFormat $format): self
    {
        $this->labelFormat = $format;

        return $this;
    }

    public function content(string $description): self
    {
        $this->content = $description;

        return $this;
    }

    /**
     * Cost centre (MPK).
     */
    public function costCenter(string $costCenter): self
    {
        $this->costCenter = $costCenter;

        return $this;
    }

    public function reference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @throws InvalidShipment When the sender or the recipient is missing or a documented rule is broken.
     */
    public function build(): Shipment
    {
        $shipment = new Shipment(
            service: $this->service,
            from: $this->from ?? throw InvalidShipment::because('the sender is not set; call from() or configure ambro.sender.'),
            to: $this->to ?? throw InvalidShipment::because('the recipient is not set; call to().'),
            parcels: $this->parcels,
            cashOnDelivery: $this->cashOnDelivery,
            insurance: $this->insurance,
            currency: $this->currency,
            additionalServices: $this->additionalServices,
            labelFormat: $this->labelFormat,
            content: $this->content,
            costCenter: $this->costCenter,
            reference: $this->reference,
        );

        $shipment->validate();

        return $shipment;
    }

    /**
     * Builds and registers the shipment.
     *
     * @throws \LogicException When the builder was created without a client.
     */
    public function send(): CreatedShipment
    {
        if ($this->client === null) {
            throw new \LogicException('This builder has no client; pass the built shipment to AmbroClient::createShipment().');
        }

        return $this->client->createShipment($this->build());
    }
}
