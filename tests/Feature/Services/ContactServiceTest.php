<?php

use App\Enums\ContactRole;
use App\Enums\CustomerType;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ContactService;
    ensureContactNumberSeries('CUSTOMER', 'CUS-');
    ensureContactNumberSeries('VENDOR', 'VEN-');
});

it('can convert a contact to a customer', function () {
    $contact = Contact::factory()->create([
        'role' => ContactRole::PROSPECT,
    ]);

    $postingGroup = CustomerPostingGroup::factory()->create();
    $businessGroup = GeneralBusinessPostingGroup::factory()->create();

    $data = [
        'customer_type' => CustomerType::WHOLESALE,
        'customer_posting_group_id' => $postingGroup->id,
        'general_business_posting_group_id' => $businessGroup->id,
    ];

    $customer = $this->service->convertToCustomer($contact, $data);

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->name)->toBe($contact->name)
        ->and($customer->customer_type)->toBe(CustomerType::WHOLESALE)
        ->and($customer->contact_id)->toBe($contact->id);

    $contact->refresh();
    expect($contact->role)->toBe(ContactRole::CUSTOMER);
});

it('can convert a contact to a vendor', function () {
    $contact = Contact::factory()->create([
        'role' => ContactRole::PROSPECT,
    ]);

    $postingGroup = VendorPostingGroup::factory()->create();
    $businessGroup = GeneralBusinessPostingGroup::factory()->create();

    $data = [
        'vendor_posting_group_id' => $postingGroup->id,
        'general_business_posting_group_id' => $businessGroup->id,
    ];

    $vendor = $this->service->convertToVendor($contact, $data);

    expect($vendor)->toBeInstanceOf(Vendor::class)
        ->and($vendor->vendor_name)->toBe($contact->name)
        ->and($vendor->contact_id)->toBe($contact->id);

    $contact->refresh();
    expect($contact->role)->toBe(ContactRole::VENDOR);
});

it('sets role to BOTH when converting a customer to a vendor', function () {
    $contact = Contact::factory()->create([
        'role' => ContactRole::CUSTOMER,
    ]);

    $postingGroup = VendorPostingGroup::factory()->create();
    $businessGroup = GeneralBusinessPostingGroup::factory()->create();

    $data = [
        'vendor_posting_group_id' => $postingGroup->id,
        'general_business_posting_group_id' => $businessGroup->id,
    ];

    $this->service->convertToVendor($contact, $data);

    $contact->refresh();
    expect($contact->role)->toBe(ContactRole::BOTH);
});

it('sets role to BOTH when converting a vendor to a customer', function () {
    $contact = Contact::factory()->create([
        'role' => ContactRole::VENDOR,
    ]);

    $postingGroup = CustomerPostingGroup::factory()->create();
    $businessGroup = GeneralBusinessPostingGroup::factory()->create();

    $data = [
        'customer_type' => CustomerType::RETAIL,
        'customer_posting_group_id' => $postingGroup->id,
        'general_business_posting_group_id' => $businessGroup->id,
    ];

    $this->service->convertToCustomer($contact, $data);

    $contact->refresh();
    expect($contact->role)->toBe(ContactRole::BOTH);
});

function ensureContactNumberSeries(string $code, string $prefix): void
{
    $series = NumberSeries::query()->updateOrCreate(
        ['code' => $code],
        [
            'description' => "{$code} test series",
            'prefix' => $prefix,
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'contacts',
        ]
    );

    $series->lines()->delete();

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => $prefix,
        'suffix' => '',
        'blocked' => false,
    ]);
}
