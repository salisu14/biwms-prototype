<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseLineType;
use App\Filament\AdminPages\PurchaseThreeWayMatch as AdminPurchaseThreeWayMatch;
use App\Filament\AdminPages\VendorSettlementHistory as AdminVendorSettlementHistory;
use App\Filament\Pages\Finance\PurchaseThreeWayMatch;
use App\Filament\Pages\Finance\VendorSettlementHistory;
use App\Http\Controllers\PurchaseThreeWayMatchExportController;
use App\Http\Controllers\VendorSettlementHistoryExportController;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\CompanyInformation;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\Role;
use App\Models\User;
use App\Services\Company\CompanyInformationService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\VendorSettlementHistoryService;
use App\Services\Purchase\PurchaseThreeWayMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\CreatesFinancialDocumentFixtures;

uses(RefreshDatabase::class, CreatesFinancialDocumentFixtures::class);

it('renders vendor settlement history traces in finance and admin and exports without changing balances', function (): void {
    $viewer = purchaseReportViewer([
        'finance.vendor_settlement_history.view',
        'finance.vendor_settlement_history.export',
        'finance.payment.apply',
    ]);

    $paymentFixture = $this->createPostedPayableFixture(1000.00);
    $businessId = Business::query()->firstOrFail()->id;
    $paymentFixture['postedInvoice']->update(['business_id' => $businessId]);
    $payment = Payment::query()->create([
        'payment_number' => 'PAY-REPORT-001',
        'payment_date' => now()->subDays(2),
        'posting_date' => now()->subDays(2),
        'status' => 'POSTED',
        'payment_amount' => 400.00,
        'party_type' => 'VENDOR',
        'party_id' => $paymentFixture['vendor']->id,
        'party_name' => $paymentFixture['vendor']->vendor_name,
        'payment_method' => 'BANK_TRANSFER',
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'payment_direction' => 'DISBURSEMENT',
        'bank_account_id' => BankAccount::factory()->paymentOnly()->create()->id,
        'applied_amount' => 0,
        'unapplied_amount' => 400.00,
        'payment_amount_lcy' => 400.00,
        'created_by' => $viewer->id,
        'business_id' => $businessId,
    ]);

    $this->ensureOpenAccountingPeriod($payment->posting_date);
    $this->ensureOpenAccountingPeriod($paymentFixture['postedInvoice']->posting_date);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $paymentFixture['postedInvoice']->id,
        'amount' => 400.00,
    ], $viewer->id);

    $creditMemoFixture = $this->createPostedPurchaseCreditMemoFixture(300.00);
    $creditMemoFixture['postedCreditMemo']->update(['business_id' => $businessId]);
    $creditMemoFixture['postedCreditMemo']->applyToInvoices([
        [
            'invoice_id' => $creditMemoFixture['postedInvoice']->id,
            'amount' => 300.00,
        ],
    ]);

    Livewire::actingAs($viewer)
        ->test(VendorSettlementHistory::class)
        ->assertSee($payment->payment_number)
        ->assertSee($paymentFixture['postedInvoice']->document_number)
        ->assertSee($creditMemoFixture['postedCreditMemo']->document_number)
        ->assertSee($creditMemoFixture['postedInvoice']->document_number);

    Livewire::actingAs($viewer)
        ->test(AdminVendorSettlementHistory::class)
        ->assertSee($payment->payment_number)
        ->assertSee($creditMemoFixture['postedCreditMemo']->document_number);

    ensurePurchaseReportCompanyProfile();
    $request = Request::create('/admin/reports/vendor-settlement-history/export', 'GET', [
        'format' => 'pdf',
        'business_id' => Business::query()->firstOrFail()->id,
    ]);
    $request->setUserResolver(fn () => $viewer);

    $pdfResponse = app()->call(
        [app(VendorSettlementHistoryExportController::class), '__invoke'],
        [
            'request' => $request,
            'service' => app(VendorSettlementHistoryService::class),
            'companyInformationService' => app(CompanyInformationService::class),
        ]
    );
    expect($pdfResponse->headers->get('content-disposition'))->toContain('vendor-settlement-history.pdf');

    $request = Request::create('/admin/reports/vendor-settlement-history/export', 'GET', [
        'format' => 'csv',
        'business_id' => Business::query()->firstOrFail()->id,
    ]);
    $request->setUserResolver(fn () => $viewer);

    $csvResponse = app()->call(
        [app(VendorSettlementHistoryExportController::class), '__invoke'],
        [
            'request' => $request,
            'service' => app(VendorSettlementHistoryService::class),
            'companyInformationService' => app(CompanyInformationService::class),
        ]
    );
    expect($csvResponse->headers->get('content-disposition'))->toContain('vendor-settlement-history.csv');
});

it('denies unauthorized access to the purchasing report exports', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = Request::create('/admin/reports/vendor-settlement-history/export', 'GET', [
        'format' => 'pdf',
    ]);
    $request->setUserResolver(fn () => $user);

    try {
        app()->call(
            [app(VendorSettlementHistoryExportController::class), '__invoke'],
            [
                'request' => $request,
                'service' => app(VendorSettlementHistoryService::class),
                'companyInformationService' => app(CompanyInformationService::class),
            ]
        );
        expect(false)->toBeTrue('Vendor settlement export should have been forbidden.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    $request = Request::create('/admin/reports/purchase-three-way-match/export', 'GET', [
        'format' => 'csv',
    ]);
    $request->setUserResolver(fn () => $user);

    try {
        app()->call(
            [app(PurchaseThreeWayMatchExportController::class), '__invoke'],
            [
                'request' => $request,
                'service' => app(PurchaseThreeWayMatchService::class),
                'companyInformationService' => app(CompanyInformationService::class),
            ]
        );
        expect(false)->toBeTrue('Purchase three-way match export should have been forbidden.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('renders the purchase three-way match report and exports direct invoice exceptions', function (): void {
    $viewer = purchaseReportViewer([
        'purchasing.purchase_three_way_match.view',
        'purchasing.purchase_three_way_match.export',
    ]);

    $fixture = $this->createPostedPayableFixture(500.00);
    $businessId = Business::query()->firstOrFail()->id;
    $item = Item::factory()->create();
    $purchaseInvoice = PurchaseInvoice::query()->create([
        'business_id' => $businessId,
        'document_number' => 'PI-REPORT-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'vendor_address' => (string) $fixture['vendor']->address,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => null,
        'posting_date' => now()->subDay(),
        'document_date' => now()->subDay(),
        'due_date' => now()->addDays(30),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => 500.00,
        'total_vat' => 0,
        'grand_total' => 500.00,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_paid' => 0,
        'remaining_amount' => 500.00,
        'paid_in_full' => false,
        'posted_by' => $viewer->id,
        'posted_at' => now()->subDay(),
        'cancelled' => false,
    ]);

    PurchaseInvoiceLine::query()->create([
        'purchase_invoice_id' => $purchaseInvoice->id,
        'po_line_id' => null,
        'po_line_number' => null,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'quantity' => 4,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 4,
        'unit_cost' => 125,
        'unit_cost_lcy' => 125,
        'line_total' => 500,
        'line_discount_amount' => 0,
        'line_discount_percent' => 0,
        'vat_code' => null,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => 500,
        'amount_including_vat_lcy' => 500,
        'line_number' => 1,
        'type' => PurchaseLineType::ITEM,
        'posting_date' => now()->subDay(),
    ]);

    Livewire::actingAs($viewer)
        ->test(PurchaseThreeWayMatch::class)
        ->assertSee($purchaseInvoice->document_number)
        ->assertSee($item->item_code)
        ->assertSee('Direct Invoice / No Receipt Match');

    Livewire::actingAs($viewer)
        ->test(AdminPurchaseThreeWayMatch::class)
        ->assertSee($purchaseInvoice->document_number)
        ->assertSee($item->item_code);

    ensurePurchaseReportCompanyProfile();
    $request = Request::create('/admin/reports/purchase-three-way-match/export', 'GET', [
        'format' => 'pdf',
        'business_id' => Business::query()->firstOrFail()->id,
    ]);
    $request->setUserResolver(fn () => $viewer);

    $pdfResponse = app()->call(
        [app(PurchaseThreeWayMatchExportController::class), '__invoke'],
        [
            'request' => $request,
            'service' => app(PurchaseThreeWayMatchService::class),
            'companyInformationService' => app(CompanyInformationService::class),
        ]
    );
    expect($pdfResponse->headers->get('content-disposition'))->toContain('purchase-three-way-match.pdf');

    $request = Request::create('/admin/reports/purchase-three-way-match/export', 'GET', [
        'format' => 'csv',
        'business_id' => Business::query()->firstOrFail()->id,
    ]);
    $request->setUserResolver(fn () => $viewer);

    $csvResponse = app()->call(
        [app(PurchaseThreeWayMatchExportController::class), '__invoke'],
        [
            'request' => $request,
            'service' => app(PurchaseThreeWayMatchService::class),
            'companyInformationService' => app(CompanyInformationService::class),
        ]
    );
    expect($csvResponse->headers->get('content-disposition'))->toContain('purchase-three-way-match.csv');
});

function purchaseReportViewer(array $permissionNames): User
{
    session()->flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $businesses = Business::query()->get();
    if ($businesses->isEmpty()) {
        $businesses = collect([Business::query()->create([
            'code' => 'REPORT-CO',
            'name' => 'Report Business',
            'is_active' => true,
        ])]);
    }

    foreach ($businesses as $business) {
        CompanyInformation::query()->firstOrCreate([
            'business_id' => $business->id,
        ], [
            'company_name' => $business->name,
            'country_code' => 'NGA',
        ]);
    }

    foreach ($permissionNames as $permissionName) {
        Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }

    $role = Role::query()->firstOrCreate([
        'name' => 'Purchase Reports Viewer',
        'guard_name' => 'web',
    ]);

    $role->syncPermissions($permissionNames);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function ensurePurchaseReportCompanyProfile(): void
{
    $businesses = Business::query()->get();
    if ($businesses->isEmpty()) {
        $businesses = collect([Business::query()->create([
            'code' => 'REPORT-CO',
            'name' => 'Report Business',
            'is_active' => true,
        ])]);
    }

    foreach ($businesses as $business) {
        CompanyInformation::query()->firstOrCreate(['business_id' => $business->id], [
            'company_name' => 'Report Test Company',
            'country_code' => 'NGA',
        ]);
    }

    if (! CompanyInformation::query()->whereNull('business_id')->exists()) {
        CompanyInformation::query()->create([
            'company_name' => 'Report Test Company',
            'country_code' => 'NGA',
        ]);
    }
}
