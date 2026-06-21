<?php

namespace App\Services\Purchase;

use App\Models\PurchaseOrder;

class PurchaseReceiptHeaderPrefillService
{
    /**
     * @param  array<string, mixed>  $existingData
     * @return array<string, mixed>
     */
    public function defaultsForPurchaseOrder(PurchaseOrder $purchaseOrder, array $existingData = []): array
    {
        $purchaseOrder->loadMissing(['vendor.contact', 'location']);

        $vendor = $purchaseOrder->vendor;
        $contact = $vendor?->contact;
        $location = $purchaseOrder->location;

        $defaults = [
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_no' => $purchaseOrder->order_number,
            'vendor_id' => $purchaseOrder->vendor_id,
            'buy_from_vendor_name' => $purchaseOrder->vendor_name,
            'buy_from_address' => $vendor?->address ?? $contact?->address,
            'buy_from_city' => $vendor?->city ?? $contact?->city,
            'buy_from_post_code' => $vendor?->postal_code ?? $contact?->post_code ?? $contact?->postal_code,
            'buy_from_country_region_code' => $vendor?->country ?? $contact?->country_region_code ?? $contact?->country,
            'buy_from_contact' => $vendor?->contact_person ?? $contact?->full_name ?? $contact?->name,
            'pay_to_vendor_no' => $vendor?->vendor_code,
            'pay_to_name' => $vendor?->vendor_name,
            'pay_to_address' => $vendor?->address ?? $contact?->address,
            'pay_to_city' => $vendor?->city ?? $contact?->city,
            'pay_to_post_code' => $vendor?->postal_code ?? $contact?->post_code ?? $contact?->postal_code,
            'pay_to_country_region_code' => $vendor?->country ?? $contact?->country_region_code ?? $contact?->country,
            'pay_to_contact' => $vendor?->contact_person ?? $contact?->full_name ?? $contact?->name,
            'ship_to_code' => $location?->code,
            'ship_to_name' => $location?->name,
            'ship_to_address' => $location?->address,
            'receiving_location_id' => $purchaseOrder->location_id,
            'location_code' => $location?->code,
            'posting_date' => optional($purchaseOrder->posting_date)->toDateString() ?? ($existingData['posting_date'] ?? null),
            'document_date' => optional($purchaseOrder->order_date)->toDateString() ?? ($existingData['document_date'] ?? null),
            'expected_receipt_date' => optional($purchaseOrder->delivery_date)->toDateString(),
            'requested_receipt_date' => optional($purchaseOrder->delivery_date)->toDateString(),
            'promised_receipt_date' => optional($purchaseOrder->delivery_date)->toDateString(),
            'currency_code' => $purchaseOrder->currency_code,
            'prices_including_vat' => $purchaseOrder->is_price_inclusive,
            'comment' => $purchaseOrder->comment,
            'buyer_id' => $purchaseOrder->created_by,
        ];

        foreach ($existingData as $key => $value) {
            if (filled($value)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
