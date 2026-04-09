<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Contact;
use App\Models\Vendor;
use App\Services\NumberSeriesService;

class VendorService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService
    ) {}

    /**
     * Create a new vendor from a contact (used when converting quote to order)
     * Following BC pattern: Create vendor from contact when no vendor exists
     */
    public function createFromContact(int $contactId): Vendor
    {
        $contact = Contact::findOrFail($contactId);

        // Check if contact is already linked to a vendor
        if ($contact->vendor_id) {
            $existingVendor = Vendor::find($contact->vendor_id);
            if ($existingVendor) {
                return $existingVendor;
            }
        }

        $vendorData = [
            'vendor_code' => $this->numberSeriesService->getNextNo('VENDOR'),
            'vendor_name' => $contact->name ?? $contact->company_name,
            'contact_person' => $contact->full_name ?? $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'mobile' => $contact->mobile,
            'address' => $contact->address,
            'city' => $contact->city,
            'state' => $contact->state ?? $contact->county,
            'postal_code' => $contact->postal_code ?? $contact->post_code,
            'country' => $contact->country ?? $contact->country_region_code,
            'tax_id' => $contact->vat_registration_no ?? $contact->tax_id,
            'payment_terms' => $contact->payment_terms,
            'currency' => $contact->currency_code ?? $contact->currency,
            'is_active' => true,
            'blocked' => false,
            // Posting groups can be set from defaults or contact
            'general_business_posting_group_id' => $contact->general_business_posting_group_id,
            'vendor_posting_group_id' => $contact->vendor_posting_group_id,
            'vat_bus_posting_group' => $contact->vat_bus_posting_group,
            'payment_terms_code' => $contact->payment_terms_code,
        ];

        $vendor = Vendor::create($vendorData);

        // Link contact to the new vendor
        $contact->update(['vendor_id' => $vendor->id]);

        return $vendor;
    }

    /**
     * Get vendor by vendor_code
     */
    public function getByCode(string $vendorCode): ?Vendor
    {
        return Vendor::where('vendor_code', $vendorCode)->first();
    }

    /**
     * Check if vendor is blocked or inactive
     */
    public function isBlocked(Vendor $vendor): bool
    {
        return $vendor->blocked || ! $vendor->is_active;
    }

    /**
     * Validate vendor for transactions
     */
    public function validateForTransaction(Vendor $vendor): void
    {
        if (! $vendor->is_active) {
            throw new \InvalidArgumentException("Vendor {$vendor->vendor_code} is inactive");
        }

        if ($vendor->blocked) {
            throw new \InvalidArgumentException("Vendor {$vendor->vendor_code} is blocked: {$vendor->blocked_reason}");
        }
    }

    /**
     * Check vendor credit limit (if applicable)
     */
    public function hasAvailableCredit(Vendor $vendor, float $requiredAmount = 0): bool
    {
        // If vendor is overpaid (negative balance), they have credit
        if ($vendor->is_overpaid) {
            return $vendor->available_credit >= $requiredAmount;
        }

        return true; // No credit limit enforcement by default
    }

    /**
     * Get default posting setup for vendor
     */
    public function getPostingSetup(Vendor $vendor): array
    {
        return [
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'vat_bus_posting_group' => $vendor->vat_bus_posting_group,
            'payables_account' => $vendor->getPayablesAccount()?->account_code,
        ];
    }

    /**
     * Block vendor with reason
     */
    public function block(Vendor $vendor, string $reason): void
    {
        $vendor->update([
            'blocked' => true,
            'blocked_reason' => $reason,
        ]);
    }

    /**
     * Unblock vendor
     */
    public function unblock(Vendor $vendor): void
    {
        $vendor->update([
            'blocked' => false,
            'blocked_reason' => null,
        ]);
    }
}
