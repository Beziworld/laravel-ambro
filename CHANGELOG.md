# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Client for all 13 operations of the Ambro Express SOAP web service (api.asmx), without ext-soap.
- Typed, validating shipment builder (packages, long packages, Size20, pallets, furniture, COD, insurance, currency, additional services).
- Labels, single labels, waybills and handover manifests.
- Courier pickups: available windows, ordering and cancellation.
- Tracking with multilingual status descriptions in Europe/Warsaw time.
- Named connections and runtime credentials; `ambro:services` artisan command.
