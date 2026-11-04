<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Enums\AdditionalService;
use Beziworld\Ambro\Enums\LabelFormat;
use Beziworld\Ambro\Enums\ParcelType;
use Beziworld\Ambro\Exceptions\InvalidShipment;
use Beziworld\Ambro\Support\Values;

/**
 * Complete shipment order (ShipmentRequest), usually produced by ShipmentBuilder.
 *
 * validate() checks the rules documented in the error list before a request
 * is sent, so that obvious mistakes do not reach the carrier.
 */
final readonly class Shipment
{
    /**
     * @param list<Parcel> $parcels
     * @param list<string> $additionalServices Codes such as SMS, EMAIL, ROD, W, M, R, Z, ZNI.
     * @param string|null $insurance Declared value as a decimal string.
     * @param string|null $currency Three-letter currency code of COD and insurance.
     * @param string|null $costCenter MPK (cost centre) printed on the documents.
     */
    public function __construct(
        public int $service,
        public Location $from,
        public Location $to,
        public array $parcels,
        public ?CashOnDelivery $cashOnDelivery = null,
        public ?string $insurance = null,
        public ?string $currency = null,
        public array $additionalServices = [],
        public LabelFormat $labelFormat = LabelFormat::Pdf,
        public ?string $content = null,
        public ?string $costCenter = null,
        public ?string $reference = null,
    ) {}

    /**
     * @throws InvalidShipment When a documented rule is broken.
     */
    public function validate(): void
    {
        foreach (['sender' => $this->from, 'recipient' => $this->to] as $role => $location) {
            $missing = $location->missingFields();

            if ($missing !== []) {
                throw InvalidShipment::because(sprintf('the %s is missing %s.', $role, implode(', ', $missing)));
            }
        }

        if (strtoupper($this->from->countryCode) !== 'PL' && strtoupper($this->to->countryCode) !== 'PL') {
            throw InvalidShipment::because('one of the shipping countries must be Poland.');
        }

        if ($this->parcels === []) {
            throw InvalidShipment::because('at least one parcel is required.');
        }

        $total = 0.0;
        $palettes = 0;

        foreach ($this->parcels as $index => $parcel) {
            if ($parcel->weight <= 0) {
                throw InvalidShipment::because(sprintf('parcel #%d must weigh more than 0 kg.', $index + 1));
            }

            $limit = $parcel->type->maxWeight();

            if ($limit !== null && $parcel->weight > $limit) {
                throw InvalidShipment::because(sprintf('parcel #%d of type %s exceeds %s kg.', $index + 1, $parcel->type->value, Values::measure($limit)));
            }

            $total += $parcel->weight;
            $palettes += $parcel->type === ParcelType::Palette ? 1 : 0;
        }

        if ($palettes > 0 && $palettes < count($this->parcels)) {
            throw InvalidShipment::because('palette and non-palette parcels cannot be mixed.');
        }

        if ($total <= 0) {
            throw InvalidShipment::because('the total weight must be greater than 0.');
        }

        if (count($this->additionalServices) !== count(array_unique($this->additionalServices))) {
            throw InvalidShipment::because('an additional service cannot be specified more than once.');
        }

        if ($this->cashOnDelivery !== null) {
            if ((float) $this->cashOnDelivery->amount < 0) {
                throw InvalidShipment::because('the COD amount cannot be negative.');
            }

            if ($this->cashOnDelivery->accountNumber === '') {
                throw InvalidShipment::because('the COD bank account number is required.');
            }

            if ($this->insurance !== null && (float) $this->insurance < (float) $this->cashOnDelivery->amount) {
                throw InvalidShipment::because('the insurance amount must be greater than or equal to the COD amount.');
            }
        }

        if ($this->insurance !== null && (float) $this->insurance < 0) {
            throw InvalidShipment::because('the insurance amount cannot be negative.');
        }

        if (in_array(AdditionalService::Email->value, $this->additionalServices, true) && trim($this->to->email) === '') {
            throw InvalidShipment::because('the EMAIL service requires the recipient e-mail.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toSoap(): array
    {
        return [
            'ServiceId' => $this->service,
            'ShipFrom' => $this->from->toSoap(),
            'ShipTo' => $this->to->toSoap(),
            'Parcels' => ['Parcel' => array_map(static fn(Parcel $parcel): array => $parcel->toSoap(), $this->parcels)],
            'Currency' => $this->currency !== null ? ['Code' => strtoupper($this->currency)] : null,
            'COD' => $this->cashOnDelivery?->toSoap(),
            'InsuranceAmount' => $this->insurance,
            'AdditionalServices' => $this->additionalServices !== []
                ? ['AdditionalService' => array_map(static fn(string $code): array => ['Code' => $code], $this->additionalServices)]
                : null,
            'LabelFormat' => $this->labelFormat->value,
            'ContentDescription' => $this->content,
            'MPK' => $this->costCenter,
            'ReferenceNumber' => $this->reference,
        ];
    }

    /**
     * Request for GetAvailableServices built from the same route, parcels and options.
     *
     * @return array<string, mixed>
     */
    public function toAvailabilityRequest(\DateTimeInterface $readyAt): array
    {
        $request = $this->toSoap();
        unset($request['ServiceId'], $request['LabelFormat'], $request['ContentDescription'], $request['MPK'], $request['ReferenceNumber']);

        return ['ReadyDate' => Values::dateTime($readyAt)] + $request;
    }
}
