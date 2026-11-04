<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Facades;

use Beziworld\Ambro\AmbroManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\Ambro\AmbroClient connection(?string $name = null)
 * @method static \Beziworld\Ambro\AmbroClient using(\Beziworld\Ambro\AmbroConfig|array<string, mixed> $config)
 * @method static string getDefaultConnection()
 * @method static list<string> connectionNames()
 * @method static void purge(?string $name = null)
 * @method static \Beziworld\Ambro\AmbroConfig config()
 * @method static \Beziworld\Ambro\ShipmentBuilder shipment()
 * @method static \Beziworld\Ambro\Data\CreatedShipment createShipment(\Beziworld\Ambro\Data\Shipment|\Beziworld\Ambro\ShipmentBuilder $shipment)
 * @method static void cancelShipments(string|list<string> $packageNumbers)
 * @method static list<\Beziworld\Ambro\Data\Label> labels(string|list<string> $packageNumbers)
 * @method static \Beziworld\Ambro\Data\Label label(string $packageNumber)
 * @method static list<\Beziworld\Ambro\Data\Label> waybills(string|list<string> $packageNumbers)
 * @method static \Beziworld\Ambro\Data\Manifest manifest(string|list<string> $packageNumbers)
 * @method static \Beziworld\Ambro\Data\Tracking tracking(string $packageNumber)
 * @method static list<\Beziworld\Ambro\Data\ServiceInfo> services()
 * @method static list<\Beziworld\Ambro\Data\ServiceInfo> servicesInfo()
 * @method static list<\Beziworld\Ambro\Data\ServiceInfo> availableServices(\Beziworld\Ambro\Data\Shipment|\Beziworld\Ambro\ShipmentBuilder $shipment, ?\DateTimeInterface $readyAt = null)
 * @method static list<\Beziworld\Ambro\Data\PickupWindow> availablePickups(?\Beziworld\Ambro\Data\Location $location = null, list<string> $packageNumbers = [], list<\Beziworld\Ambro\Data\Parcel> $parcels = [])
 * @method static string callPickup(\Beziworld\Ambro\Data\Pickup $pickup)
 * @method static void cancelPickups(string|list<string> $pickupNumbers)
 * @method static array<array-key, mixed> call(string $operation, array<string, mixed> $parameters = [], bool $read = false)
 *
 * @see AmbroManager
 * @see \Beziworld\Ambro\AmbroClient
 */
final class Ambro extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AmbroManager::class;
    }
}
