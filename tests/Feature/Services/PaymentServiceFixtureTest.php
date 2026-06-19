<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedSalesCreditMemo;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies a seeded customer receipt to a posted sales invoice', function (): void {
    $fixture = $this->createPostedReceivableApplicationFixture();
    grantFixturePaymentApplyPermission($fixture['user']);

    $application = app(PaymentService::class)->applyToDocument(
        $fixture['payment'],
        [
            'document_type' => 'SALES_INVOICE',
            'document_id' => $fixture['postedInvoice']->id,
            'amount' => 30000.00,
        ],
        $fixture['user']->id,
    );

    $fixture['postedInvoice']->refresh();
    $fixture['payment']->refresh();

    expect((float) $application->amount_applied)->toBe(30000.00)
        ->and((float) $fixture['postedInvoice']->amount_paid)->toBe(30000.00)
        ->and((float) $fixture['postedInvoice']->remaining_amount)->toBe(7670.40)
        ->and((float) $fixture['payment']->applied_amount)->toBe(30000.00)
        ->and((float) $fixture['payment']->unapplied_amount)->toBe(0.00);
});

it('applies a seeded vendor payment to a posted purchase invoice', function (): void {
    $fixture = $this->createPostedPayableApplicationFixture();
    grantFixturePaymentApplyPermission($fixture['user']);

    $application = app(PaymentService::class)->applyToDocument(
        $fixture['payment'],
        [
            'document_type' => 'PURCHASE_INVOICE',
            'document_id' => $fixture['postedInvoice']->id,
            'amount' => 200000.00,
        ],
        $fixture['user']->id,
    );

    $fixture['postedInvoice']->refresh();
    $fixture['payment']->refresh();

    expect((float) $application->amount_applied)->toBe(200000.00)
        ->and((float) $fixture['postedInvoice']->amount_paid)->toBe(200000.00)
        ->and((float) $fixture['postedInvoice']->remaining_amount)->toBe(50000.00)
        ->and((float) $fixture['payment']->applied_amount)->toBe(200000.00)
        ->and((float) $fixture['payment']->unapplied_amount)->toBe(0.00);
});

it('provides posted sales and purchase credit memo fixtures for credit scenarios', function (): void {
    $salesCreditMemoFixture = $this->createPostedSalesCreditMemoFixture();
    $purchaseCreditMemoFixture = $this->createPostedPurchaseCreditMemoFixture();

    expect($salesCreditMemoFixture['postedCreditMemo'])->toBeInstanceOf(PostedSalesCreditMemo::class)
        ->and($salesCreditMemoFixture['postedCreditMemo']->corrected_invoice_id)->toBe($salesCreditMemoFixture['postedInvoice']->id)
        ->and($purchaseCreditMemoFixture['postedCreditMemo'])->toBeInstanceOf(PostedPurchaseCreditMemo::class)
        ->and($purchaseCreditMemoFixture['postedCreditMemo']->vendor_id)->toBe($purchaseCreditMemoFixture['vendor']->id);
});

it('processes a refund against a posted sales credit memo', function (): void {
    $fixture = $this->createPostedSalesCreditMemoFixture();

    $fixture['postedCreditMemo']->processRefund(2500.00, 'REF-PSCM-001');
    $fixture['postedCreditMemo']->refresh();

    expect($fixture['postedCreditMemo']->refunded)->toBeTrue()
        ->and((float) $fixture['postedCreditMemo']->refund_amount)->toBe(2500.00)
        ->and($fixture['postedCreditMemo']->refund_reference)->toBe('REF-PSCM-001')
        ->and($fixture['postedCreditMemo']->refunded_at)->not->toBeNull();
});

it('applies a posted purchase credit memo to an open purchase invoice ledger entry', function (): void {
    $fixture = $this->createPostedPurchaseCreditMemoFixture();
    $invoiceLedgerEntryId = VendorLedgerEntry::query()
        ->where('vendor_id', $fixture['vendor']->id)
        ->where('document_type', 'PURCHASE_INVOICE')
        ->value('id');

    $fixture['postedCreditMemo']->applyToInvoices([
        [
            'entry_id' => $invoiceLedgerEntryId,
            'amount' => 50000.00,
        ],
    ]);

    $fixture['documentEntry']->refresh();

    $invoiceLedgerEntry = VendorLedgerEntry::query()
        ->where('vendor_id', $fixture['vendor']->id)
        ->where('document_type', 'PURCHASE_INVOICE')
        ->firstOrFail();

    expect((float) $fixture['documentEntry']->remaining_amount)->toBe(0.00)
        ->and($fixture['documentEntry']->fully_applied)->toBeTrue()
        ->and((float) $invoiceLedgerEntry->remaining_amount)->toBe(50000.00)
        ->and($invoiceLedgerEntry->open)->toBeTrue();
});

function grantFixturePaymentApplyPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.apply',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.payment.apply');
}
