<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\CompanyInformation;
use App\Models\Item;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseCreditMemoLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Company\CompanyInformationService;
use App\Services\Print\PurchaseDocumentPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renders printable purchase receipt credit memo and vendor payment documents', function (): void {
    $vendor = Vendor::factory()->create();
    $item = Item::factory()->create([
        'item_code' => 'ITEM-1000',
        'description' => 'Mai Sasanci',
    ]);
    $location = Location::factory()->create([
        'code' => 'LOC-01',
        'name' => 'Main Warehouse',
    ]);
    $user = User::factory()->create();
    $this->actingAs($user);

    $receipt = PurchaseReceipt::query()->create([
        'document_number' => 'P-REC-1001',
        'vendor_id' => $vendor->id,
        'purchase_order_no' => 'PO-1001',
        'posting_date' => now()->subDay(),
        'document_date' => now()->subDay(),
        'receiving_location_id' => $location->id,
        'buy_from_vendor_name' => $vendor->vendor_name,
        'posted' => true,
        'actual_receipt_date' => now()->subDay(),
        'created_by' => $user->id,
        'status' => 'POSTED',
    ]);

    PurchaseReceiptLine::query()->create([
        'purchase_receipt_id' => $receipt->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'no' => $item->item_code,
        'description' => 'Mai Sasanci',
        'unit_of_measure_code' => 'PCS',
        'quantity' => 5,
        'quantity_received' => 5,
        'direct_unit_cost' => 150,
        'line_amount' => 750,
        'purchase_order_id' => null,
        'purchase_order_line_id' => null,
    ]);

    $creditMemo = PostedPurchaseCreditMemo::query()->create([
        'document_number' => 'P-CM-1001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'corrects_invoice_number' => 'P-INV-1001',
        'posting_date' => now()->subDay(),
        'document_date' => now()->subDay(),
        'location_code' => $location->code,
        'subtotal' => 750,
        'tax_amount' => 0,
        'grand_total' => 750,
        'posted' => true,
        'posted_at' => now()->subDay(),
        'posted_by' => $user->id,
    ]);

    PostedPurchaseCreditMemoLine::query()->create([
        'credit_memo_id' => $creditMemo->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => 'Mai Sasanci',
        'quantity' => 5,
        'unit_of_measure' => 'PCS',
        'unit_price' => 150,
        'amount' => 750,
        'line_total' => 750,
        'tax_percent' => 0,
        'tax_amount' => 0,
        'grand_total' => 750,
    ]);

    $payment = Payment::factory()->create([
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'party_name' => $vendor->vendor_name,
        'payment_direction' => 'DISBURSEMENT',
        'payment_amount' => 1500,
        'applied_amount' => 1500,
        'unapplied_amount' => 0,
        'status' => 'POSTED',
        'posted_at' => now(),
        'posting_date' => now()->subDay(),
    ]);

    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => 999999,
        'document_number' => 'P-INV-1001',
        'document_original_amount' => 1500,
        'document_remaining_before' => 1500,
        'amount_applied' => 1500,
        'amount_applied_lcy' => 1500,
        'document_remaining_after' => 0,
        'full_payment' => true,
        'applied_by' => $user->id,
        'applied_at' => now()->subMinutes(5),
        'reversed' => false,
    ]);

    $service = app(PurchaseDocumentPrintService::class);

    $receiptPdf = $service->generatePurchaseReceipt($receipt)->output();
    $creditMemoPdf = $service->generatePostedPurchaseCreditMemo($creditMemo)->output();
    $paymentPdf = $service->generateVendorPaymentReceipt($payment)->output();

    expect(strlen($receiptPdf))->toBeGreaterThan(0)
        ->and(strlen($creditMemoPdf))->toBeGreaterThan(0)
        ->and(strlen($paymentPdf))->toBeGreaterThan(0);
});

it('uses the canonical business company header and thermal layouts without fallback to the first business', function (): void {
    Storage::fake('public');

    $logoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2X+foAAAAASUVORK5CYII=');

    $primaryBusiness = Business::query()->create([
        'code' => 'ACME',
        'name' => 'Acme Holdings Limited',
        'is_active' => true,
    ]);
    $otherBusiness = Business::query()->create([
        'code' => 'BETA',
        'name' => 'Beta Manufacturing Plc',
        'is_active' => true,
    ]);

    CompanyInformation::query()->updateOrCreate([
        'business_id' => $primaryBusiness->id,
    ], [
        'company_name' => 'Acme Holdings Limited',
        'trading_name' => 'Acme Trading',
        'registration_no' => 'RC-ACME-001',
        'tax_registration_no' => 'TIN-ACME-001',
        'address_line_1' => '1 Acme Street',
        'city' => 'Lagos',
        'state_province' => 'Lagos',
        'postal_code' => '101001',
        'country_code' => 'NGA',
        'phone_no' => '+234-1-111-1111',
        'email' => 'info@acme.example',
        'website' => 'https://acme.example',
        'invoice_footer' => 'Acme footer',
        'logo_path' => 'company/logos/acme.png',
    ]);

    CompanyInformation::query()->updateOrCreate([
        'business_id' => $otherBusiness->id,
    ], [
        'company_name' => 'Beta Manufacturing Plc',
        'trading_name' => 'Beta Trade',
        'registration_no' => 'RC-BETA-002',
        'tax_registration_no' => 'TIN-BETA-002',
        'address_line_1' => '99 Beta Road',
        'city' => 'Abuja',
        'state_province' => 'FCT',
        'postal_code' => '900001',
        'country_code' => 'NGA',
        'phone_no' => '+234-9-999-9999',
        'email' => 'hello@beta.example',
        'website' => 'https://beta.example',
        'invoice_footer' => 'Beta footer',
        'logo_path' => 'company/logos/beta.png',
    ]);

    Storage::disk('public')->put('company/logos/acme.png', $logoBytes);
    Storage::disk('public')->put('company/logos/beta.png', $logoBytes);

    session(['active_business_id' => $primaryBusiness->id]);

    $vendor = Vendor::factory()->create();
    $item = Item::factory()->create([
        'item_code' => 'ITEM-2000',
        'description' => 'Thermal Test Item',
    ]);
    $location = Location::factory()->create([
        'code' => 'LOC-02',
        'name' => 'Secondary Warehouse',
    ]);
    $user = User::factory()->create();
    $this->actingAs($user);

    $receipt = PurchaseReceipt::query()->create([
        'document_number' => 'P-REC-2001',
        'vendor_id' => $vendor->id,
        'purchase_order_no' => 'PO-2001',
        'posting_date' => now()->subDay(),
        'document_date' => now()->subDay(),
        'receiving_location_id' => $location->id,
        'buy_from_vendor_name' => $vendor->vendor_name,
        'posted' => true,
        'actual_receipt_date' => now()->subDay(),
        'created_by' => $user->id,
        'status' => 'POSTED',
    ]);

    PurchaseReceiptLine::query()->create([
        'purchase_receipt_id' => $receipt->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'no' => $item->item_code,
        'description' => 'Thermal Test Item',
        'unit_of_measure_code' => 'PCS',
        'quantity' => 5,
        'quantity_received' => 5,
        'direct_unit_cost' => 150,
        'line_amount' => 750,
        'purchase_order_id' => null,
        'purchase_order_line_id' => null,
    ]);

    $creditMemo = PostedPurchaseCreditMemo::query()->create([
        'document_number' => 'P-CM-2001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'corrects_invoice_number' => 'P-INV-2001',
        'posting_date' => now()->subDay(),
        'document_date' => now()->subDay(),
        'location_code' => $location->code,
        'subtotal' => 750,
        'tax_amount' => 0,
        'grand_total' => 750,
        'posted' => true,
        'posted_at' => now()->subDay(),
        'posted_by' => $user->id,
    ]);

    PostedPurchaseCreditMemoLine::query()->create([
        'credit_memo_id' => $creditMemo->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => 'Thermal Test Item',
        'quantity' => 5,
        'unit_of_measure' => 'PCS',
        'unit_price' => 150,
        'amount' => 750,
        'line_total' => 750,
        'tax_percent' => 0,
        'tax_amount' => 0,
        'grand_total' => 750,
    ]);

    $payment = Payment::factory()->create([
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'party_name' => $vendor->vendor_name,
        'payment_direction' => 'DISBURSEMENT',
        'payment_amount' => 1500,
        'applied_amount' => 1500,
        'unapplied_amount' => 0,
        'status' => 'POSTED',
        'posted_at' => now(),
        'posting_date' => now()->subDay(),
    ]);

    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => 999999,
        'document_number' => 'P-INV-2001',
        'document_original_amount' => 1500,
        'document_remaining_before' => 1500,
        'amount_applied' => 1500,
        'amount_applied_lcy' => 1500,
        'document_remaining_after' => 0,
        'full_payment' => true,
        'applied_by' => $user->id,
        'applied_at' => now()->subMinutes(5),
        'reversed' => false,
    ]);

    $companyHeader = app(CompanyInformationService::class)->getReportHeader(session('active_business_id'));

    expect($companyHeader['name'])->toBe('Acme Trading')
        ->and($companyHeader['registration_no'])->toBe('RC-ACME-001')
        ->and($companyHeader['tax_no'])->toBe('TIN-ACME-001')
        ->and($companyHeader['logo_data_uri'])->not->toBeNull();

    $paymentA4Html = view('pdf.vendor-payment-receipt', [
        'payment' => $payment,
        'applications' => collect([
            [
                'applied_at' => now()->format('d/m/Y H:i'),
                'document_number' => 'P-INV-2001',
                'document_type' => 'PURCHASE_INVOICE',
                'amount_applied' => 1500,
                'remaining_after' => 0,
            ],
        ]),
        'company' => $companyHeader,
    ])->render();

    $payment80Html = view('pdf.vendor-payment-receipt-thermal', [
        'payment' => $payment,
        'applications' => collect([
            [
                'applied_at' => now()->format('d/m/Y H:i'),
                'document_number' => 'P-INV-2001',
                'document_type' => 'PURCHASE_INVOICE',
                'amount_applied' => 1500,
                'remaining_after' => 0,
            ],
        ]),
        'company' => $companyHeader,
        'format' => '80mm',
    ])->render();

    $payment58Html = view('pdf.vendor-payment-receipt-thermal', [
        'payment' => $payment,
        'applications' => collect([
            [
                'applied_at' => now()->format('d/m/Y H:i'),
                'document_number' => 'P-INV-2001',
                'document_type' => 'PURCHASE_INVOICE',
                'amount_applied' => 1500,
                'remaining_after' => 0,
            ],
        ]),
        'company' => $companyHeader,
        'format' => '58mm',
    ])->render();

    $receipt80Html = view('pdf.purchase-receipt-thermal', [
        'receipt' => $receipt,
        'company' => $companyHeader,
        'format' => '80mm',
    ])->render();

    $receipt58Html = view('pdf.purchase-receipt-thermal', [
        'receipt' => $receipt,
        'company' => $companyHeader,
        'format' => '58mm',
    ])->render();

    $vendorSettlementHtml = view('pdf.vendor-settlement-history', [
        'rows' => collect(),
        'filters' => [],
        'generatedAt' => now(),
        'company' => $companyHeader,
    ])->render();

    $threeWayMatchHtml = view('pdf.purchase-three-way-match', [
        'report' => [
            'title' => 'Purchase Three-Way Match',
            'summary' => [
                'total_rows' => 0,
                'matched_count' => 0,
                'exception_count' => 0,
                'ordered_value' => 0,
                'received_value' => 0,
                'invoiced_value' => 0,
            ],
            'rows' => collect(),
            'filters' => [],
        ],
        'generatedAt' => now(),
        'company' => $companyHeader,
    ])->render();

    expect($paymentA4Html)
        ->toContain('Acme Trading')
        ->toContain('1 Acme Street')
        ->toContain('TIN/VAT')
        ->not->toContain('Beta Manufacturing Plc')
        ->and($payment80Html)
        ->toContain('Acme Trading')
        ->toContain('data:image/png;base64')
        ->and($payment58Html)
        ->toContain('Acme Trading')
        ->not->toContain('Beta Manufacturing Plc')
        ->and($receipt80Html)
        ->toContain('Acme Trading')
        ->toContain('1 Acme Street')
        ->and($receipt58Html)
        ->toContain('Acme Trading')
        ->not->toContain('Beta Manufacturing Plc')
        ->and($vendorSettlementHtml)
        ->toContain('Acme Trading')
        ->toContain('1 Acme Street')
        ->and($threeWayMatchHtml)
        ->toContain('Acme Trading')
        ->toContain('1 Acme Street');

    $paymentPdf80 = app(PurchaseDocumentPrintService::class)->generateVendorPaymentReceipt80mm($payment)->output();
    $paymentPdf58 = app(PurchaseDocumentPrintService::class)->generateVendorPaymentReceipt58mm($payment)->output();
    $receiptPdf80 = app(PurchaseDocumentPrintService::class)->generatePurchaseReceiptThermal80mm($receipt)->output();
    $receiptPdf58 = app(PurchaseDocumentPrintService::class)->generatePurchaseReceiptThermal58mm($receipt)->output();

    expect(strlen($paymentPdf80))->toBeGreaterThan(0)
        ->and(strlen($paymentPdf58))->toBeGreaterThan(0)
        ->and(strlen($receiptPdf80))->toBeGreaterThan(0)
        ->and(strlen($receiptPdf58))->toBeGreaterThan(0);
});
