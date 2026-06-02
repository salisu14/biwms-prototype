<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Item;
use App\Models\Payment;
use App\Models\BankAccount;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a vendor with synchronized posting-group metadata and contact defaults', function (): void {
    $vendor = Vendor::factory()->create();

    $vendor->refresh();

    expect($vendor->generalBusinessPostingGroup)->not->toBeNull()
        ->and($vendor->vendorPostingGroup)->not->toBeNull()
        ->and($vendor->contact)->not->toBeNull()
        ->and($vendor->gen_bus_posting_group)->toBe($vendor->generalBusinessPostingGroup?->code)
        ->and($vendor->vendor_posting_group)->toBe($vendor->vendorPostingGroup?->code)
        ->and($vendor->contact?->vendor_posting_group_id)->toBe($vendor->vendor_posting_group_id)
        ->and($vendor->contact?->general_business_posting_group_id)->toBe($vendor->general_business_posting_group_id);
});

it('creates items against reusable default posting groups', function (): void {
    $firstItem = Item::factory()->create();
    $secondItem = Item::factory()->create();

    expect($firstItem->generalProductPostingGroup)->not->toBeNull()
        ->and($firstItem->inventoryPostingGroup)->not->toBeNull()
        ->and($secondItem->general_product_posting_group_id)->toBe($firstItem->general_product_posting_group_id)
        ->and($secondItem->inventory_posting_group_id)->toBe($firstItem->inventory_posting_group_id);
});

it('creates vendor payments with a valid vendor, currency, and creator context', function (): void {
    $payment = Payment::factory()->create([
        'payment_amount' => 125000,
    ]);

    $payment->refresh();

    expect($payment->vendor()->first())->not->toBeNull()
        ->and($payment->currency)->not->toBeNull()
        ->and($payment->creator)->not->toBeNull()
        ->and($payment->party_type)->toBe('VENDOR')
        ->and($payment->payment_direction)->toBe('DISBURSEMENT')
        ->and($payment->party_name)->toBe($payment->vendor()->first()?->vendor_name)
        ->and($payment->currency_code)->toBe('NGN');
});

it('creates customers and customer receipt payments with valid ar setup', function (): void {
    $customer = Customer::factory()->create();
    $payment = Payment::factory()
        ->customerReceipt()
        ->create([
            'party_id' => $customer->id,
            'payment_amount' => 37670.40,
        ]);

    $customer->refresh();
    $payment->refresh();

    expect($customer->generalBusinessPostingGroup)->not->toBeNull()
        ->and($customer->customerPostingGroup)->not->toBeNull()
        ->and($customer->contact)->not->toBeNull()
        ->and($customer->contact?->general_business_posting_group_id)->toBe($customer->general_business_posting_group_id)
        ->and($payment->customer()?->first())->not->toBeNull()
        ->and($payment->party_type)->toBe('CUSTOMER')
        ->and($payment->payment_direction)->toBe('RECEIPT')
        ->and($payment->party_name)->toBe($payment->customer()?->first()?->name)
        ->and($payment->currency_code)->toBe('NGN');
});

it('creates bank accounts that are ready for payment and reconciliation tests', function (): void {
    $bankAccount = BankAccount::factory()->receiptOnly()->create();

    $bankAccount->refresh();

    expect($bankAccount->glAccount)->not->toBeNull()
        ->and($bankAccount->currency)->not->toBeNull()
        ->and($bankAccount->active)->toBeTrue()
        ->and($bankAccount->allow_receipts)->toBeTrue()
        ->and($bankAccount->allow_payments)->toBeFalse();
});

it('creates customers with seeded ledger history for subledger scenarios', function (): void {
    $customer = Customer::factory()->customerWithLedgerHistory()->create();

    $ledgerEntries = CustomerLedgerEntry::query()
        ->where('customer_id', $customer->id)
        ->orderBy('entry_number')
        ->get();

    expect($ledgerEntries)->toHaveCount(2)
        ->and($ledgerEntries[0]->document_type)->toBe('SALES_INVOICE')
        ->and((float) $ledgerEntries[0]->remaining_amount)->toBe(7670.40)
        ->and($ledgerEntries[1]->document_type)->toBe('PAYMENT')
        ->and($ledgerEntries[1]->fully_applied)->toBeTrue()
        ->and((float) $ledgerEntries[1]->running_balance)->toBe(7670.40);
});
