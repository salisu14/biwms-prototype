<?php

use App\Enums\AccountCategory;
use App\Enums\IncomeBalanceType;
use App\Models\ChartOfAccount;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\VendorLedgerEntry;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reverses a customer ledger entry through a balanced linked gl transaction', function (): void {
    $this->ensureOpenAccountingPeriod(now());
    $fixture = $this->createPostedReceivableFixture(1000.00);
    $receivables = $fixture['customer']->getReceivablesAccount();
    $revenue = ChartOfAccount::factory()->create();

    $transaction = app(GeneralLedgerService::class)->postTransaction([
        ['account_id' => $receivables->id, 'debit_amount' => 1000, 'credit_amount' => 0, 'source_type' => 'CUSTOMER'],
        ['account_id' => $revenue->id, 'debit_amount' => 0, 'credit_amount' => 1000, 'source_type' => 'ITEM'],
    ], [
        'source_module' => 'sales',
        'source_type' => 'CUSTOMER',
        'source_id' => $fixture['customer']->id,
        'source_number' => 'AR-REVERSAL-001',
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'AR-REVERSAL-001',
        'posting_date' => now(),
        'actor_id' => $fixture['user']->id,
        'idempotency_key' => 'test-customer-reversal-gl',
    ]);
    $original = CustomerLedgerEntry::query()->create([
        'entry_number' => 2,
        'customer_id' => $fixture['customer']->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'AR-REVERSAL-001',
        'description' => 'Reversal fixture',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 1000,
        'credit_amount' => 0,
        'amount' => 1000,
        'running_balance' => 1000,
        'remaining_amount' => 1000,
        'open' => true,
        'currency_code' => 'NGN',
        'original_debit_amount' => 1000,
        'original_credit_amount' => 0,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $fixture['customer']->general_business_posting_group_id,
        'customer_posting_group_id' => $fixture['customer']->customer_posting_group_id,
        'gl_entry_id' => $transaction->glEntries->firstWhere('chart_of_account_id', $receivables->id)->id,
        'source_type' => $fixture['postedInvoice']::class,
        'source_id' => $fixture['postedInvoice']->id,
        'created_by' => $fixture['user']->id,
    ]);

    $reversal = $original->reverse($fixture['user']->id, 'Correction');

    expect($reversal->gl_entry_id)->not->toBeNull()
        ->and($original->fresh()->reversed)->toBeTrue()
        ->and(CustomerLedgerEntry::query()->where('document_number', 'REV-AR-REVERSAL-001')->count())->toBe(1)
        ->and((float) GlEntry::query()->where('posting_transaction_id', $transaction->id)->sum('amount'))->toBe(0.0)
        ->and((float) GlEntry::query()->where('document_number', 'REV-AR-REVERSAL-001')->sum('debit_amount'))->toBe(1000.0)
        ->and((float) GlEntry::query()->where('document_number', 'REV-AR-REVERSAL-001')->sum('credit_amount'))->toBe(1000.0)
        ->and(fn () => $original->fresh()->reverse($fixture['user']->id, 'Duplicate'))
        ->toThrow(Exception::class, 'already reversed');
});

it('reverses a vendor ledger entry without editing or deleting the original', function (): void {
    $this->ensureOpenAccountingPeriod(now());
    $fixture = $this->createPostedPayableFixture(1000.00);
    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $fixture['vendor']->vendorPostingGroup()->update(['payables_account_id' => $payables->id]);
    $payables = $fixture['vendor']->fresh()->getPayablesAccount();
    $expense = ChartOfAccount::factory()->create();

    $transaction = app(GeneralLedgerService::class)->postTransaction([
        ['account_id' => $expense->id, 'debit_amount' => 1000, 'credit_amount' => 0, 'source_type' => 'ITEM'],
        ['account_id' => $payables->id, 'debit_amount' => 0, 'credit_amount' => 1000, 'source_type' => 'VENDOR'],
    ], [
        'source_module' => 'purchasing',
        'source_type' => 'VENDOR',
        'source_id' => $fixture['vendor']->id,
        'source_number' => 'AP-REVERSAL-001',
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'AP-REVERSAL-001',
        'posting_date' => now(),
        'actor_id' => $fixture['user']->id,
        'idempotency_key' => 'test-vendor-reversal-gl',
    ]);
    $original = VendorLedgerEntry::query()->create([
        'entry_number' => 2,
        'vendor_id' => $fixture['vendor']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'AP-REVERSAL-001',
        'description' => 'Reversal fixture',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 1000,
        'amount' => -1000,
        'running_balance' => -1000,
        'remaining_amount' => 1000,
        'open' => true,
        'currency_code' => 'NGN',
        'original_debit_amount' => 0,
        'original_credit_amount' => 1000,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'gl_entry_id' => $transaction->glEntries->firstWhere('chart_of_account_id', $payables->id)->id,
        'source_type' => $fixture['postedInvoice']::class,
        'source_id' => $fixture['postedInvoice']->id,
        'created_by' => $fixture['user']->id,
    ]);

    $reversal = $original->reverse($fixture['user']->id, 'Correction');

    expect($reversal->gl_entry_id)->not->toBeNull()
        ->and($original->fresh()->reversed)->toBeTrue()
        ->and(VendorLedgerEntry::query()->where('document_number', 'REV-AP-REVERSAL-001')->count())->toBe(1)
        ->and((float) GlEntry::query()->where('document_number', 'REV-AP-REVERSAL-001')->sum('debit_amount'))->toBe(1000.0)
        ->and((float) GlEntry::query()->where('document_number', 'REV-AP-REVERSAL-001')->sum('credit_amount'))->toBe(1000.0)
        ->and(fn () => $original->fresh()->reverse($fixture['user']->id, 'Duplicate'))
        ->toThrow(Exception::class, 'already reversed');
});
