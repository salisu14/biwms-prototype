<?php

declare(strict_types=1);

use App\Filament\Pages\Finance\CustomerSettlementHistory;
use App\Http\Controllers\CustomerSettlementHistoryExportController;
use App\Models\Business;
use App\Models\CustomerLedgerApplication;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\CustomerSettlementHistoryService;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\CreatesFinancialDocumentFixtures;

uses(RefreshDatabase::class, CreatesFinancialDocumentFixtures::class);

it('renders canonical payment and credit memo settlement traces without mutating balances', function (): void {
    $viewer = financeSettlementHistoryViewer();

    $paymentFixture = $this->createPostedReceivableApplicationFixture(1000.00, 400.00);
    app(PaymentService::class)->applyToDocument($paymentFixture['payment'], [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $paymentFixture['postedInvoice']->id,
        'amount' => 400.00,
    ], $viewer->id);

    $creditMemoFixture = $this->createPostedSalesCreditMemoFixture(300.00);
    $creditMemoFixture['postedCreditMemo']->applyToInvoices([
        [
            'invoice_id' => $creditMemoFixture['postedInvoice']->id,
            'amount' => 300.00,
        ],
    ]);

    $paymentRows = app(CustomerSettlementHistoryService::class)->rows([
        'customer_id' => $paymentFixture['customer']->id,
    ]);

    $creditMemoRows = app(CustomerSettlementHistoryService::class)->rows([
        'customer_id' => $creditMemoFixture['customer']->id,
        'settlement_type' => 'CREDIT_MEMO_APPLICATION',
    ]);

    expect($paymentRows)->not->toBeEmpty()
        ->and($creditMemoRows)->not->toBeEmpty()
        ->and(DB::table('payment_applications')->count())->toBe(1)
        ->and(CustomerLedgerApplication::query()->count())->toBe(1);

    Livewire::actingAs($viewer)
        ->test(CustomerSettlementHistory::class)
        ->set('customer_id', $paymentFixture['customer']->id)
        ->assertSee($paymentFixture['payment']->payment_number)
        ->assertSee($paymentFixture['postedInvoice']->document_number);

    Livewire::actingAs($viewer)
        ->test(CustomerSettlementHistory::class)
        ->set('customer_id', $creditMemoFixture['customer']->id)
        ->assertSee($creditMemoFixture['postedCreditMemo']->document_number)
        ->assertSee($creditMemoFixture['postedInvoice']->document_number);
});

it('filters settlement history by customer type source target date currency and business', function (): void {
    $viewer = financeSettlementHistoryViewer();
    $businessId = Business::query()->value('id');

    $paymentFixture = $this->createPostedReceivableApplicationFixture(2000.00, 500.00);
    app(PaymentService::class)->applyToDocument($paymentFixture['payment'], [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $paymentFixture['postedInvoice']->id,
        'amount' => 500.00,
    ], $viewer->id);

    $paymentFixture['postedInvoice']->forceFill([
        'dimensions' => ['business_id' => $businessId],
    ])->save();
    $paymentFixture['payment']->forceFill([
        'dimensions' => ['business_id' => $businessId],
    ])->save();

    $creditMemoFixture = $this->createPostedSalesCreditMemoFixture(300.00);
    $creditMemoFixture['postedCreditMemo']->applyToInvoices([
        [
            'invoice_id' => $creditMemoFixture['postedInvoice']->id,
            'amount' => 300.00,
        ],
    ]);
    $creditMemoFixture['postedCreditMemo']->forceFill([
        'dimensions' => ['business_id' => $businessId],
    ])->save();
    $creditMemoFixture['postedInvoice']->forceFill([
        'dimensions' => ['business_id' => $businessId],
    ])->save();

    $customer = $paymentFixture['customer'];

    Livewire::actingAs($viewer)
        ->test(CustomerSettlementHistory::class)
        ->set('customer_id', $customer->id)
        ->set('settlement_type', 'PAYMENT_APPLICATION')
        ->set('source_document_number', $paymentFixture['payment']->payment_number)
        ->set('target_document_number', $paymentFixture['postedInvoice']->document_number)
        ->set('date_from', now()->subMonth()->toDateString())
        ->set('date_to', now()->addDay()->toDateString())
        ->set('currency_code', 'ngn')
        ->set('business_id', $businessId)
        ->assertSee($paymentFixture['payment']->payment_number)
        ->assertSee($paymentFixture['postedInvoice']->document_number)
        ->assertDontSee($creditMemoFixture['postedCreditMemo']->document_number);
});

it('exports settlement history as csv and xlsx for authorized finance users', function (): void {
    $viewer = financeSettlementHistoryViewer();

    $paymentFixture = $this->createPostedReceivableApplicationFixture(1000.00, 400.00);
    app(PaymentService::class)->applyToDocument($paymentFixture['payment'], [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $paymentFixture['postedInvoice']->id,
        'amount' => 400.00,
    ], $viewer->id);

    $request = Request::create('/admin/reports/customer-settlement-history/export', 'GET', [
        'customer_id' => $paymentFixture['customer']->id,
        'format' => 'xlsx',
    ]);
    $request->setUserResolver(fn () => $viewer);

    $xlsxResponse = app(CustomerSettlementHistoryExportController::class)($request, app(CustomerSettlementHistoryService::class));
    expect($xlsxResponse->headers->get('content-disposition'))->toContain('customer-settlement-history.xlsx');

    $request = Request::create('/admin/reports/customer-settlement-history/export', 'GET', [
        'customer_id' => $paymentFixture['customer']->id,
        'format' => 'csv',
    ]);
    $request->setUserResolver(fn () => $viewer);

    $response = app(CustomerSettlementHistoryExportController::class)($request, app(CustomerSettlementHistoryService::class));

    expect($response->headers->get('content-disposition'))->toContain('customer-settlement-history.csv');
});

it('denies settlement history access to users without finance access', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CustomerSettlementHistory::class)
        ->assertForbidden();

    $request = Request::create('/admin/reports/customer-settlement-history/export', 'GET', [
        'format' => 'csv',
    ]);
    $request->setUserResolver(fn () => $user);

    expect(fn () => app(CustomerSettlementHistoryExportController::class)($request, app(CustomerSettlementHistoryService::class)))
        ->toThrow(HttpException::class);
});

function financeSettlementHistoryViewer(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::query()->firstOrCreate([
        'name' => 'finance.customer_settlement_history.view',
        'guard_name' => 'web',
    ]);
    Permission::query()->firstOrCreate([
        'name' => 'finance.customer_settlement_history.export',
        'guard_name' => 'web',
    ]);
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.apply',
        'guard_name' => 'web',
    ]);

    Role::query()->firstOrCreate([
        'name' => 'finance-manager',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole('finance-manager');
    $user->givePermissionTo([
        'finance.customer_settlement_history.view',
        'finance.customer_settlement_history.export',
        'finance.payment.apply',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}
