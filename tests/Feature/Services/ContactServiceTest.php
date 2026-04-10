<?php

use App\Enums\ContactRole;
use App\Enums\CustomerType;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ContactService;
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
