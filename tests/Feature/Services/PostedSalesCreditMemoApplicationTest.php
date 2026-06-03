<?php

declare(strict_types=1);

use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies a posted sales credit memo to an open sales invoice and keeps invoice totals in sync', function (): void {
    $fixture = $this->createPostedSalesCreditMemoFixture();
    $appliedAmount = 5000.00;
    $expectedRemainingAmount = max(0, (float) $fixture['postedInvoice']->grand_total - $appliedAmount);

    $fixture['postedCreditMemo']->applyToInvoices([
        [
            'invoice_id' => $fixture['postedInvoice']->id,
            'amount' => $appliedAmount,
        ],
    ]);

    $fixture['postedCreditMemo']->refresh();
    $fixture['postedInvoice']->refresh();
    $fixture['documentEntry']->refresh();

    $invoiceLedgerEntry = CustomerLedgerEntry::query()
        ->where('customer_id', $fixture['customer']->id)
        ->where('document_type', 'SALES_INVOICE')
        ->firstOrFail();

    $applicationEntry = CustomerLedgerEntry::query()
        ->where('customer_id', $fixture['customer']->id)
        ->where('document_type', 'CREDIT_MEMO_APPLICATION')
        ->where('document_number', $fixture['postedCreditMemo']->document_number)
        ->firstOrFail();

    expect($fixture['postedCreditMemo'])->toBeInstanceOf(PostedSalesCreditMemo::class)
        ->and((float) $fixture['postedCreditMemo']->amount_applied)->toBe($appliedAmount)
        ->and((float) $fixture['postedCreditMemo']->remaining_amount)->toBe(0.00)
        ->and($fixture['postedCreditMemo']->fully_applied)->toBeTrue()
        ->and((float) $fixture['documentEntry']->remaining_amount)->toBe(0.00)
        ->and($fixture['documentEntry']->fully_applied)->toBeTrue()
        ->and((float) $fixture['postedInvoice']->amount_paid)->toBe($appliedAmount)
        ->and((float) $fixture['postedInvoice']->remaining_amount)->toBe($expectedRemainingAmount)
        ->and((float) $invoiceLedgerEntry->remaining_amount)->toBe($expectedRemainingAmount)
        ->and($invoiceLedgerEntry->open)->toBeTrue()
        ->and((float) $applicationEntry->credit_amount)->toBe($appliedAmount)
        ->and($applicationEntry->open)->toBeFalse();
});
