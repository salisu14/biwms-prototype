<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\ShipmentMethod;
use App\Models\Vendor;
use Illuminate\Support\Facades\Cache;

class ShipmentMethodService
{
    private const CACHE_TTL = 3600;

    /**
     * Get default shipment method for customer (BC: Get Cust. Default)
     */
    public function getDefaultForCustomer(Customer $customer): ?ShipmentMethod
    {
        if ($customer->shipment_method_code) {
            return $this->getByCode($customer->shipment_method_code);
        }

        // Fallback to country/region default
        return $this->getDefaultForCountry($customer->country_code);
    }

    /**
     * Get default shipment method for vendor
     */
    public function getDefaultForVendor(Vendor $vendor): ?ShipmentMethod
    {
        if ($vendor->shipment_method_code) {
            return $this->getByCode($vendor->shipment_method_code);
        }

        return $this->getDefaultForCountry($vendor->country_code);
    }

    /**
     * Apply shipment method to sales order (BC: Copy from Cust.)
     */
    public function applyToSalesOrder(SalesOrder $order, ?string $shipmentMethodCode = null): void
    {
        $customer = $order->customer;

        $method = $shipmentMethodCode
            ? $this->getByCode($shipmentMethodCode)
            : $this->getDefaultForCustomer($customer);

        if (!$method || !$method->canUse()) {
            throw new \InvalidArgumentException("Invalid or blocked shipment method: {$shipmentMethodCode}");
        }

        $order->update([
            'shipment_method_code' => $method->code,
            'shipping_agent_code' => $order->shipping_agent_code ?? $method->default_shipping_agent_id,
            'shipping_agent_service_code' => $order->shipping_agent_service_code ?? $method->default_service_code,
        ]);

        // Validate Incoterm compatibility
        if ($method->is_incoterm && $order->ship_to_country_code) {
            if (!$method->isValidForRoute($customer->country_code, $order->ship_to_country_code)) {
                throw new \InvalidArgumentException(
                    "Incoterm {$method->incoterm_code} is not valid for this route"
                );
            }
        }
    }

    /**
     * Apply shipment method to purchase order
     */
    public function applyToPurchaseOrder(PurchaseOrder $order, ?string $shipmentMethodCode = null): void
    {
        $vendor = $order->vendor;

        $method = $shipmentMethodCode
            ? $this->getByCode($shipmentMethodCode)
            : $this->getDefaultForVendor($vendor);

        if (!$method || !$method->canUse()) {
            throw new \InvalidArgumentException("Invalid or blocked shipment method");
        }

        $order->update([
            'shipment_method_code' => $method->code,
        ]);
    }

    /**
     * Validate and resolve shipment method for document
     */
    public function resolveForDocument(string $code, ?string $entityType = null, ?int $entityId = null): ShipmentMethod
    {
        $method = $this->getByCode($code);

        if (!$method) {
            throw new \InvalidArgumentException("Shipment method {$code} not found");
        }

        if (!$method->canUse()) {
            throw new \InvalidArgumentException("Shipment method {$code} is blocked or inactive");
        }

        // Entity-specific validation
        if ($entityType && $entityId) {
            $entity = match ($entityType) {
                'customer' => Customer::find($entityId),
                'vendor' => Vendor::find($entityId),
                default => null,
            };

            if ($entity && $entity->shipment_method_code !== $code) {
                // Allow override, but could log warning
            }
        }

        return $method;
    }

    /**
     * Get Incoterm guidance for shipment method
     */
    public function getIncotermGuidance(ShipmentMethod $method): array
    {
        if (!$method->is_incoterm) {
            return [];
        }

        $guidance = [
            'EXW' => [
                'seller_obligation' => 'Make goods available at premises',
                'buyer_obligation' => 'All costs and risks from pickup',
                'suitable_for' => 'Domestic or buyer has export capability',
            ],
            'FOB' => [
                'seller_obligation' => 'Deliver goods on board vessel',
                'buyer_obligation' => 'Freight, insurance, and risk from port',
                'suitable_for' => 'Sea freight, seller has export license',
            ],
            'CIF' => [
                'seller_obligation' => 'Cost, insurance, and freight to port',
                'buyer_obligation' => 'Risk from port of destination',
                'suitable_for' => 'Sea freight, seller controls shipping',
            ],
            'DDP' => [
                'seller_obligation' => 'Deliver duty paid to destination',
                'buyer_obligation' => 'Unloading only',
                'suitable_for' => 'Seller has import capability in destination',
            ],
        ];

        return $guidance[$method->incoterm_code] ?? [];
    }

    /**
     * Seed standard shipment methods (BC: Standard Setup)
     */
    public function seedStandardMethods(): void
    {
        $standardMethods = [
            // Basic methods
            ['code' => 'PICKUP', 'description' => 'Customer Pickup', 'transport_mode' => 'none'],
            ['code' => 'COURIER', 'description' => 'Courier Service', 'transport_mode' => 'road'],
            ['code' => 'TRUCK', 'description' => 'Truck Delivery', 'transport_mode' => 'road'],

            // Incoterms 2020
            ['code' => 'EXW', 'description' => 'Ex Works', 'is_incoterm' => true, 'incoterm_code' => 'EXW', 'seller_pays_freight' => false, 'seller_pays_insurance' => false],
            ['code' => 'FCA', 'description' => 'Free Carrier', 'is_incoterm' => true, 'incoterm_code' => 'FCA', 'seller_pays_freight' => false, 'seller_pays_insurance' => false],
            ['code' => 'FOB', 'description' => 'Free On Board', 'is_incoterm' => true, 'incoterm_code' => 'FOB', 'transport_mode' => 'sea', 'seller_pays_freight' => false, 'seller_pays_insurance' => false],
            ['code' => 'CIF', 'description' => 'Cost Insurance Freight', 'is_incoterm' => true, 'incoterm_code' => 'CIF', 'transport_mode' => 'sea', 'seller_pays_freight' => true, 'seller_pays_insurance' => true],
            ['code' => 'DAP', 'description' => 'Delivered at Place', 'is_incoterm' => true, 'incoterm_code' => 'DAP', 'seller_pays_freight' => true, 'seller_pays_insurance' => false],
            ['code' => 'DDP', 'description' => 'Delivered Duty Paid', 'is_incoterm' => true, 'incoterm_code' => 'DDP', 'seller_pays_freight' => true, 'seller_pays_insurance' => true, 'seller_pays_duty' => true],
        ];

        foreach ($standardMethods as $method) {
            ShipmentMethod::firstOrCreate(
                ['code' => $method['code']],
                array_merge($method, ['is_active' => true])
            );
        }
    }

    // Private methods
    private function getByCode(string $code): ?ShipmentMethod
    {
        return Cache::remember("shipment_method.{$code}", self::CACHE_TTL, function () use ($code) {
            return ShipmentMethod::where('code', $code)->first();
        });
    }

    private function getDefaultForCountry(?string $countryCode): ?ShipmentMethod
    {
        // Could implement country-specific defaults
        return ShipmentMethod::active()
            ->where('code', 'COURIER') // Default fallback
            ->first();
    }
}
