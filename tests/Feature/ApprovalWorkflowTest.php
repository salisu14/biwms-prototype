<?php

use App\Enums\PurchaseQuoteStatus;
use App\Models\Contact;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\PurchaseQuote;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\Approval\ApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents non-approver from approving an entry', function () {
    $approver = User::factory()->create();
    $other = User::factory()->create();

    $gbpg = GeneralBusinessPostingGroup::create(['code' => 'TEST', 'description' => 'Test']);
    $vpg = VendorPostingGroup::create(['code' => 'VPG', 'description' => 'Vendor PG']);
    $contact = Contact::create(['name' => 'Test Contact']);

    $vendor = Vendor::create([
        'vendor_code' => 'V-001',
        'vendor_name' => 'Test Vendor',
        'general_business_posting_group_id' => $gbpg->id,
        'vendor_posting_group_id' => $vpg->id,
        'contact_id' => $contact->id,
        'is_active' => true,
        'blocked' => false,
    ]);

    $quote = PurchaseQuote::create([
        'document_no' => 'T-100',
        'vendor_id' => $vendor->id,
        'buyer_id' => $other->id,
        'status' => PurchaseQuoteStatus::OPEN,
        'document_date' => now(),
    ]);

    $entry = $quote->approvalEntries()->create([
        'sequence_no' => 1,
        'approver_id' => $approver->id,
        'status' => 'created',
    ]);

    $this->actingAs($other);

    $this->expectException(AuthorizationException::class);

    app(ApprovalService::class)->approve($entry);
});

it('allows approver to approve and releases when last entry', function () {
    $approver = User::factory()->create();

    $gbpg = GeneralBusinessPostingGroup::create(['code' => 'TEST2', 'description' => 'Test']);
    $vpg = VendorPostingGroup::create(['code' => 'VPG2', 'description' => 'Vendor PG']);
    $contact = Contact::create(['name' => 'Test Contact 2']);

    $vendor = Vendor::create([
        'vendor_code' => 'V-002',
        'vendor_name' => 'Test Vendor 2',
        'general_business_posting_group_id' => $gbpg->id,
        'vendor_posting_group_id' => $vpg->id,
        'contact_id' => $contact->id,
        'is_active' => true,
        'blocked' => false,
    ]);

    $quote = PurchaseQuote::create([
        'document_no' => 'T-200',
        'vendor_id' => $vendor->id,
        'buyer_id' => $approver->id,
        'status' => PurchaseQuoteStatus::OPEN,
        'document_date' => now(),
    ]);

    $entry = $quote->approvalEntries()->create([
        'sequence_no' => 1,
        'approver_id' => $approver->id,
        'status' => 'created',
    ]);

    $this->actingAs($approver);

    app(ApprovalService::class)->approve($entry);

    $entry->refresh();
    $quote->refresh();

    expect($entry->isApproved())->toBeTrue();
    expect($quote->isPendingApproval())->toBeFalse();
    expect($quote->released_by)->toBe($approver->id);
});
