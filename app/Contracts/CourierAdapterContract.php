<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Shipment;

interface CourierAdapterContract
{
    public function code(): string;

    public function name(): string;

    /** ['pickup','labels','tracking','manifest','ndr','cod','cancel']. */
    public function supports(): array;

    public function credentialSchema(): array;

    /** Health-check the credentials — returns [ok => bool, message => string]. */
    public function testConnection(): array;

    /**
     * Check serviceability for an order (or a raw pickup/delivery pair).
     * Returns provider-normalized data. Never throws.
     */
    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weight = 0.5, int $codAmount = 0): array;

    /** Create an order/shipment at the provider. Returns raw provider payload. */
    public function createShipment(Order $order): array;

    /** Assign an AWB to a provider shipment id. Optional courier_id. */
    public function assignAwb(Shipment $shipment, ?string $courierId = null): array;

    /** Request pickup for a shipment. */
    public function requestPickup(Shipment $shipment): array;

    /** Generate a shipping label URL. */
    public function generateLabel(Shipment $shipment): array;

    /** Generate an invoice URL (some providers only). */
    public function generateInvoice(Shipment $shipment): array;

    /** Pull latest tracking info. */
    public function track(Shipment $shipment): array;

    /** Cancel the shipment. */
    public function cancel(Shipment $shipment, ?string $reason = null): array;

    /**
     * Extract a normalized shipment status from a provider status string.
     * Returns one of Shipment::STATUS_* or null.
     */
    public function normalizeStatus(?string $providerStatus): ?string;
}
