<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchaseReceiptHeaderPrefillService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prefills purchase receipt header defaults from the purchase order and vendor details', function (): void {
    $user = User::factory()->create();
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic',
        'blocked' => false,
    ]);
    $vendorPostingGroup = VendorPostingGroup::factory()->create();
    $contact = Contact::query()->create([
        'name' => 'Bifli Vendor Contact',
        'full_name' => 'Bifli Vendor Contact',
        'company_name' => 'Bifli Vendor Ltd',
        'role' => 'vendor',
        'type' => 'company',
        'address' => '12 Supply Road',
        'city' => 'Lagos',
        'post_code' => '100001',
        'country_region_code' => 'NG',
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => 'DOMESTIC',
    ]);
    $vendor = Vendor::factory()->create([
        'vendor_name' => 'Bifli Vendor Ltd',
        'address' => '12 Supply Road',
        'city' => 'Lagos',
        'postal_code' => '100001',
        'country' => 'NG',
        'contact_person' => 'Bifli Vendor Contact',
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'gen_bus_posting_group' => $generalBusinessPostingGroup->code,
        'vendor_posting_group' => $vendorPostingGroup->code,
        'vat_bus_posting_group' => 'DOMESTIC',
        'contact_id' => $contact->id,
        'is_price_inclusive' => true,
    ]);
    $location = Location::factory()->create([
        'code' => 'MAIN',
        'name' => 'Main Warehouse',
        'address' => 'Warehouse Street',
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'order_number' => 'PO-HEAD-0001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => '2026-06-01',
        'posting_date' => '2026-06-02',
        'delivery_date' => '2026-06-05',
        'location_id' => $location->id,
        'currency_code' => 'NGN',
        'comment' => 'Deliver urgently',
        'created_by' => $user->id,
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => 'DOMESTIC',
        'is_price_inclusive' => true,
    ]);

    $defaults = app(PurchaseReceiptHeaderPrefillService::class)->defaultsForPurchaseOrder($purchaseOrder);

    expect($defaults['purchase_order_no'])->toBe('PO-HEAD-0001')
        ->and($defaults['vendor_id'])->toBe($vendor->id)
        ->and($defaults['buy_from_vendor_name'])->toBe('Bifli Vendor Ltd')
        ->and($defaults['buy_from_address'])->toBe('12 Supply Road')
        ->and($defaults['pay_to_vendor_no'])->toBe($vendor->vendor_code)
        ->and($defaults['pay_to_name'])->toBe('Bifli Vendor Ltd')
        ->and($defaults['receiving_location_id'])->toBe($location->id)
        ->and($defaults['location_code'])->toBe('MAIN')
        ->and($defaults['ship_to_name'])->toBe('Main Warehouse')
        ->and($defaults['ship_to_address'])->toBe('Warehouse Street')
        ->and($defaults['posting_date'])->toBe('2026-06-02')
        ->and($defaults['document_date'])->toBe('2026-06-01')
        ->and($defaults['expected_receipt_date'])->toBe('2026-06-05')
        ->and($defaults['requested_receipt_date'])->toBe('2026-06-05')
        ->and($defaults['promised_receipt_date'])->toBe('2026-06-05')
        ->and($defaults['currency_code'])->toBe('NGN')
        ->and($defaults['prices_including_vat'])->toBeTrue()
        ->and($defaults['comment'])->toBe('Deliver urgently')
        ->and($defaults['buyer_id'])->toBe($user->id);
});
