<?php

use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\PurchaseOrderStatus;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseCreditMemo;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchases\PurchaseCreditMemoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('purchase invoice posting creates traceable item, value, vendor, and balanced gl entries using base quantity', function () {
    $fixture = purchasePostingFixture();
    $this->actingAs($fixture['user']);

    $invoice = PurchaseInvoice::query()->create([
        'document_number' => 'PI-TRACE-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => $fixture['location']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => 1000,
        'total_vat' => 0,
        'grand_total' => 1000,
        'remaining_amount' => 1000,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'quantity_base' => 288,
        'unit_cost' => 1000,
        'unit_cost_lcy' => 1000,
        'line_total' => 1000,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 1000,
        'amount_including_vat_lcy' => 1000,
        'posting_date' => $invoice->posting_date,
    ]);

    $postedInvoice = app(PurchaseInvoiceService::class)->post($invoice);

    $invoice->refresh();
    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', 'PI-TRACE-001')
        ->firstOrFail();

    expect($postedInvoice)->toBeInstanceOf(PostedPurchaseInvoice::class)
        ->and($invoice->status)->toBe(ApprovalStatus::POSTED)
        ->and((float) $itemLedgerEntry->quantity)->toBe(288.0)
        ->and((float) $itemLedgerEntry->remaining_quantity)->toBe(288.0)
        ->and($itemLedgerEntry->entry_type)->toBe(ItemLedgerEntryType::PURCHASE)
        ->and((float) $itemLedgerEntry->cost_amount_actual)->toBe(1000.0)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(288.0);

    expect(ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->where('document_no', 'PI-TRACE-001')
        ->where('quantity', 288)
        ->where('cost_amount_actual', 1000)
        ->exists())->toBeTrue();

    expect(VendorLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', 'PI-TRACE-001')
        ->where('vendor_id', $fixture['vendor']->id)
        ->exists())->toBeTrue();

    $postedLine = $postedInvoice->lines()->firstOrFail();
    expect((float) $postedLine->quantity)->toBe(1.0)
        ->and((float) $postedLine->quantity_base)->toBe(288.0)
        ->and($postedLine->item_ledger_entry_id)->toBe($itemLedgerEntry->id);

    $glEntries = GlEntry::query()->where('document_number', 'PI-TRACE-001')->get();
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2));

    $this->expectExceptionMessage('Purchase invoice is already posted.');
    app(PurchaseInvoiceService::class)->post($invoice->fresh());
});

test('purchase receipt increases inventory and purchase invoice from receipt does not double inventory', function () {
    $fixture = purchasePostingFixture();
    ensurePurchaseInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = PurchaseOrder::query()->create([
        'order_number' => 'PO-RECEIPT-001',
        'status' => PurchaseOrderStatus::APPROVED,
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'payment_terms' => 30,
        'currency_code' => 'NGN',
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'total_amount' => 1000,
        'total_vat' => 0,
        'grand_total' => 1000,
        'created_by' => $fixture['user']->id,
    ]);

    $line = $order->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'received_quantity' => 0.5,
        'unit_of_measure' => 'CT',
        'unit_cost' => 1000,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
    ]);

    app(PurchaseOrderService::class)->postReceipt($order);

    $receiptEntry = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_RECEIPT')
        ->where('document_number', 'PO-RECEIPT-001')
        ->firstOrFail();

    expect((float) $receiptEntry->quantity)->toBe(144.0)
        ->and((float) $receiptEntry->cost_amount_actual)->toBe(500.0)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(144.0)
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $receiptEntry->entry_number)->exists())->toBeTrue();

    $line->fresh()->update(['received_quantity' => 1]);
    app(PurchaseOrderService::class)->postReceipt($order->fresh());

    $receiptEntry = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_RECEIPT')
        ->where('document_number', 'PO-RECEIPT-001')
        ->orderByDesc('id')
        ->firstOrFail();

    expect((float) $receiptEntry->quantity)->toBe(144.0)
        ->and((float) $receiptEntry->cost_amount_actual)->toBe(500.0)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(288.0)
        ->and(ItemLedgerEntry::query()
            ->where('document_type', 'PURCHASE_RECEIPT')
            ->where('document_number', 'PO-RECEIPT-001')
            ->count())->toBe(2);

    expect(fn () => app(PurchaseOrderService::class)->postReceipt($order->fresh()))
        ->toThrow(Exception::class, 'already been posted');

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh());
    $postedInvoice = app(PurchaseInvoiceService::class)->post($invoice);

    expect(ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', $invoice->document_number)
        ->exists())->toBeFalse()
        ->and(ItemLedgerEntry::query()
            ->where('document_type', 'PURCHASE_RECEIPT')
            ->where('document_number', 'PO-RECEIPT-001')
            ->count())->toBe(2)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(288.0);

    $receiptEntryIds = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_RECEIPT')
        ->where('document_number', 'PO-RECEIPT-001')
        ->pluck('id');

    expect($receiptEntryIds)->toContain($invoice->fresh('lines')->lines->first()->item_ledger_entry_id)
        ->and($receiptEntryIds)->toContain($postedInvoice->fresh('lines')->lines->first()->item_ledger_entry_id)
        ->and((float) $line->fresh()->invoiced_quantity)->toBe(1.0);

    expect(VendorLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', $invoice->document_number)
        ->where('vendor_id', $fixture['vendor']->id)
        ->exists())->toBeTrue();
});

test('direct purchase invoice increases inventory once and service lines do not affect inventory', function () {
    $fixture = purchasePostingFixture();
    $this->actingAs($fixture['user']);

    $serviceItem = Item::query()->create([
        'item_code' => 'SVC-PI',
        'description' => 'Consulting Service',
        'item_type' => ItemType::SERVICE,
        'unit_cost' => 50,
        'inventory' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
    ]);

    $invoice = PurchaseInvoice::query()->create([
        'document_number' => 'PI-DIRECT-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => $fixture['location']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => 1050,
        'total_vat' => 0,
        'grand_total' => 1050,
        'remaining_amount' => 1050,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'quantity_base' => 288,
        'unit_cost' => 1000,
        'unit_cost_lcy' => 1000,
        'line_total' => 1000,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 1000,
        'amount_including_vat_lcy' => 1000,
        'posting_date' => $invoice->posting_date,
    ]);

    $invoice->lines()->create([
        'line_number' => 20000,
        'item_id' => $serviceItem->id,
        'item_code' => $serviceItem->item_code,
        'item_description' => $serviceItem->description,
        'general_product_posting_group_id' => $serviceItem->general_product_posting_group_id,
        'inventory_posting_group_id' => null,
        'quantity' => 1,
        'unit_of_measure_code' => 'EA',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_cost' => 50,
        'unit_cost_lcy' => 50,
        'line_total' => 50,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 50,
        'amount_including_vat_lcy' => 50,
        'posting_date' => $invoice->posting_date,
    ]);

    app(PurchaseInvoiceService::class)->post($invoice);

    expect(ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', 'PI-DIRECT-001')
        ->count())->toBe(1)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(288.0)
        ->and((float) $serviceItem->fresh()->inventory)->toBe(0.0)
        ->and(ItemLedgerEntry::query()
            ->where('document_number', 'PI-DIRECT-001')
            ->where('item_id', $serviceItem->id)
            ->exists())->toBeFalse();
});

test('purchase invoice posting rejects missing exact posting setup and rolls back ledger creation', function () {
    $fixture = purchasePostingFixture(createGeneralPostingSetup: false);
    $this->actingAs($fixture['user']);

    $invoice = PurchaseInvoice::query()->create([
        'document_number' => 'PI-MISSING-SETUP',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => $fixture['location']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => 1000,
        'total_vat' => 0,
        'grand_total' => 1000,
        'remaining_amount' => 1000,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'quantity_base' => 288,
        'unit_cost' => 1000,
        'unit_cost_lcy' => 1000,
        'line_total' => 1000,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 1000,
        'amount_including_vat_lcy' => 1000,
        'posting_date' => $invoice->posting_date,
    ]);

    expect(fn () => app(PurchaseInvoiceService::class)->post($invoice))
        ->toThrow(Exception::class, 'Posting setup missing');

    expect($invoice->fresh()->status)->toBe(ApprovalStatus::APPROVED)
        ->and(ItemLedgerEntry::query()->where('document_number', 'PI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', 'PI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'PI-MISSING-SETUP')->exists())->toBeFalse()
        ->and(VendorLedgerEntry::query()->where('document_number', 'PI-MISSING-SETUP')->exists())->toBeFalse();
});

test('purchase credit memo reverses inventory, value, vendor, and gl entries using base quantity', function () {
    $fixture = purchasePostingFixture();
    grantPurchaseCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);
    $fixture['item']->forceFill(['inventory' => 288])->save();

    $memo = PurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-TRACE-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'status' => ApprovalStatus::APPROVED,
        'currency_code' => 'NGN',
        'description' => 'Return to vendor',
    ]);

    $memo->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_cost' => 1000,
        'tax_percent' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'unit_of_measure_code' => 'CT',
    ]);

    $postedMemo = app(PurchaseCreditMemoService::class)->post($memo);

    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_CREDIT_MEMO')
        ->where('document_number', 'PCM-TRACE-001')
        ->firstOrFail();

    expect($postedMemo)->toBeInstanceOf(PostedPurchaseCreditMemo::class)
        ->and($memo->fresh()->status)->toBe(ApprovalStatus::POSTED)
        ->and((float) $itemLedgerEntry->quantity)->toBe(-288.0)
        ->and($itemLedgerEntry->entry_type)->toBe(ItemLedgerEntryType::PURCHASE)
        ->and((float) $fixture['item']->fresh()->inventory)->toBe(0.0);

    expect(ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->where('document_no', 'PCM-TRACE-001')
        ->where('quantity', -288)
        ->exists())->toBeTrue();

    expect(VendorLedgerEntry::query()
        ->where('document_type', 'PURCHASE_CREDIT_MEMO')
        ->where('document_number', 'PCM-TRACE-001')
        ->where('vendor_id', $fixture['vendor']->id)
        ->exists())->toBeTrue();

    $glEntries = GlEntry::query()->where('document_number', 'PCM-TRACE-001')->get();
    expect(round((float) $glEntries->sum('debit_amount'), 2))
        ->toBe(round((float) $glEntries->sum('credit_amount'), 2));

    expect(fn () => app(PurchaseCreditMemoService::class)->post($memo->fresh()))
        ->toThrow(Exception::class, 'Purchase credit memo is already posted.');
});

test('linked purchase credit memo reduces payable returns stock and blocks over-returning', function () {
    $fixture = purchasePostingFixture();
    grantPurchaseCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    $invoice = PurchaseInvoice::query()->create([
        'document_number' => 'PI-RETURN-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => $fixture['location']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => 1000,
        'total_vat' => 0,
        'grand_total' => 1000,
        'remaining_amount' => 1000,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'quantity_base' => 288,
        'unit_cost' => 1000,
        'unit_cost_lcy' => 1000,
        'line_total' => 1000,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 1000,
        'amount_including_vat_lcy' => 1000,
        'posting_date' => $invoice->posting_date,
    ]);

    $postedInvoice = app(PurchaseInvoiceService::class)->post($invoice);

    expect((float) $fixture['item']->fresh()->inventory)->toBe(288.0)
        ->and((float) VendorLedgerEntry::query()->where('vendor_id', $fixture['vendor']->id)->sum('amount'))->toBe(1000.0);

    $memo = PurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-RETURN-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'corrects_invoice_id' => $invoice->id,
        'corrects_invoice_number' => $postedInvoice->document_number,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'status' => ApprovalStatus::APPROVED,
        'currency_code' => 'NGN',
        'description' => 'Return to vendor',
    ]);

    $memo->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_cost' => 1000,
        'tax_percent' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'unit_of_measure_code' => 'CT',
    ]);

    $postedMemo = app(PurchaseCreditMemoService::class)->post($memo);

    expect((float) $fixture['item']->fresh()->inventory)->toBe(0.0)
        ->and((float) VendorLedgerEntry::query()->where('vendor_id', $fixture['vendor']->id)->sum('amount'))->toBe(0.0)
        ->and($postedMemo->corrects_invoice_number)->toBe('PI-RETURN-001')
        ->and(ItemLedgerEntry::query()
            ->where('document_type', 'PURCHASE_CREDIT_MEMO')
            ->where('document_number', 'PCM-RETURN-001')
            ->where('quantity', -288)
            ->exists())->toBeTrue();

    $overReturnMemo = PurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-OVER-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'corrects_invoice_id' => $invoice->id,
        'corrects_invoice_number' => $postedInvoice->document_number,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'status' => ApprovalStatus::APPROVED,
        'currency_code' => 'NGN',
        'description' => 'Return again',
    ]);

    $overReturnMemo->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_cost' => 1000,
        'tax_percent' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'unit_of_measure_code' => 'CT',
    ]);

    expect(fn () => app(PurchaseCreditMemoService::class)->post($overReturnMemo))
        ->toThrow(ValidationException::class, 'exceeds invoiced quantity');
});

test('purchase credit memo posting requires permission and rolls back on missing setup', function () {
    $fixture = purchasePostingFixture(createGeneralPostingSetup: false);
    $this->actingAs($fixture['user']);
    $fixture['item']->forceFill(['inventory' => 288])->save();

    $memo = PurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-MISSING-SETUP',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'status' => ApprovalStatus::APPROVED,
        'currency_code' => 'NGN',
        'description' => 'Return to vendor',
    ]);

    $memo->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_cost' => 1000,
        'tax_percent' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'unit_of_measure_code' => 'CT',
    ]);

    expect(fn () => app(PurchaseCreditMemoService::class)->post($memo))
        ->toThrow(AuthorizationException::class);

    grantPurchaseCreditMemoPostPermission($fixture['user']);

    expect(fn () => app(PurchaseCreditMemoService::class)->post($memo->fresh()))
        ->toThrow(Exception::class, 'Posting setup missing');

    expect($memo->fresh()->status)->toBe(ApprovalStatus::APPROVED)
        ->and(ItemLedgerEntry::query()->where('document_number', 'PCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('document_no', 'PCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'PCM-MISSING-SETUP')->exists())->toBeFalse()
        ->and(VendorLedgerEntry::query()->where('document_number', 'PCM-MISSING-SETUP')->exists())->toBeFalse();
});

/**
 * @return array{user: User, vendor: Vendor, item: Item, location: Location}
 */
function purchasePostingFixture(bool $createGeneralPostingSetup = true): array
{
    $user = User::factory()->create();
    $location = Location::factory()->create(['code' => 'MAIN']);

    $payablesAccount = purchasePostingTestAccount('2100', 'Accounts Payable', 'payable', IncomeBalanceType::BALANCE_SHEET);
    $inventoryAccount = purchasePostingTestAccount('1200', 'Inventory', 'inventory', IncomeBalanceType::BALANCE_SHEET);
    $purchaseAccount = purchasePostingTestAccount('5100', 'Purchases', 'direct_expense', IncomeBalanceType::INCOME_STATEMENT);

    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic',
        'blocked' => false,
    ]);
    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Goods',
        'blocked' => false,
    ]);
    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Goods',
        'blocked' => false,
    ]);
    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Vendors',
        'payables_account_id' => $payablesAccount->id,
        'blocked' => false,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => null,
        'inventory_account_id' => $inventoryAccount->id,
    ]);

    if ($createGeneralPostingSetup) {
        GeneralPostingSetup::query()->create([
            'general_business_posting_group_id' => $businessGroup->id,
            'general_product_posting_group_id' => $productGroup->id,
            'purchase_account_id' => $purchaseAccount->id,
            'blocked' => false,
        ]);
    }

    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'is_base_uom' => true,
    ]);
    $cartonUom = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'is_base_uom' => false,
    ]);

    $item = Item::query()->create([
        'item_code' => 'RM-CT',
        'description' => 'Raw Carton Item',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $baseUom->id,
        'unit_cost' => 10,
        'inventory' => 0,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $cartonUom->id,
        'uom_type' => 'PURCHASE',
        'conversion_factor' => 288,
        'is_default' => true,
    ]);

    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    return compact('user', 'vendor', 'item', 'location');
}

function purchasePostingTestAccount(
    string $number,
    string $name,
    string $category,
    IncomeBalanceType $incomeBalance,
): ChartOfAccount {
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'income_balance' => $incomeBalance,
        'direct_posting' => true,
        'blocked' => false,
    ]);
}

function grantPurchaseCreditMemoPostPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'purchase.credit_memo.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('purchase.credit_memo.post');
}

function ensurePurchaseInvoiceNumberSeries(): void
{
    $series = NumberSeries::query()->updateOrCreate(
        ['code' => 'P-INV'],
        [
            'description' => 'Purchase Invoice test series',
            'prefix' => 'PINV-',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'purchase',
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
        'prefix' => 'PINV-',
        'suffix' => '',
        'blocked' => false,
    ]);
}
