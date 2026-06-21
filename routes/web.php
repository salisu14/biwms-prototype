<?php

use App\Http\Controllers\Auth\SuperAdminTwoFactorChallengeController;
use App\Http\Controllers\Auth\SuperAdminTwoFactorSetupController;
use App\Http\Controllers\BalanceSheetPrintController;
use App\Http\Controllers\CashFlowStatementPrintController;
use App\Http\Controllers\CustomerSubledgerSummaryPrintController;
use App\Http\Controllers\DepreciationBookReportPrintController;
use App\Http\Controllers\ExpenseReportExportController;
use App\Http\Controllers\FixedAssetLedgerEntriesPrintController;
use App\Http\Controllers\FixedAssetListPrintController;
use App\Http\Controllers\GroupSummaryPrintController;
use App\Http\Controllers\ItemLedgerSummaryPrintController;
use App\Http\Controllers\PhysicalInventoryJournalPrintController;
use App\Http\Controllers\ProfitAndLossPrintController;
use App\Http\Controllers\VoucherPrintController;
use App\Http\Controllers\WaybillController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/super-admin/two-factor/setup', [SuperAdminTwoFactorSetupController::class, 'create'])
        ->name('super-admin-2fa.setup.create');
    Route::post('/super-admin/two-factor/setup', [SuperAdminTwoFactorSetupController::class, 'store'])
        ->name('super-admin-2fa.setup.store');
    Route::get('/super-admin/two-factor/challenge', [SuperAdminTwoFactorChallengeController::class, 'create'])
        ->name('super-admin-2fa.challenge.create');
    Route::post('/super-admin/two-factor/challenge', [SuperAdminTwoFactorChallengeController::class, 'store'])
        ->name('super-admin-2fa.challenge.store');
});

Route::get('/admin/sales-shipments/{shipment}/waybill', [WaybillController::class, 'print'])
    ->name('waybill.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/group-summary/print', GroupSummaryPrintController::class)
    ->name('reports.group-summary.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/balance-sheet/print', BalanceSheetPrintController::class)
    ->name('reports.balance-sheet.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/profit-and-loss/print', ProfitAndLossPrintController::class)
    ->name('reports.profit-and-loss.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/cash-flow/print', CashFlowStatementPrintController::class)
    ->name('reports.cash-flow.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/cash-flow/export', CashFlowStatementPrintController::class)
    ->name('reports.cash-flow.export')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/fixed-asset-list/print', FixedAssetListPrintController::class)
    ->name('reports.fixed-asset-list.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/depreciation-book/print', DepreciationBookReportPrintController::class)
    ->name('reports.depreciation-book.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/fixed-asset-ledger/export', FixedAssetLedgerEntriesPrintController::class)
    ->name('reports.fixed-asset-ledger.export')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/customer-subledger-summary/print', CustomerSubledgerSummaryPrintController::class)
    ->name('reports.customer-subledger-summary.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/item-ledger-summary/print', ItemLedgerSummaryPrintController::class)
    ->name('reports.item-ledger-summary.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/physical-inventory-journals/{journal}/print', [PhysicalInventoryJournalPrintController::class, 'print'])
    ->name('physical-inventory.print')
    ->middleware(['web', 'auth']);

Route::get('/admin/reports/expense/export', ExpenseReportExportController::class)
    ->name('reports.expense.export')
    ->middleware(['web', 'auth']);

Route::middleware(['auth'])->prefix('petty-cash')->group(function () {
    Route::get('vouchers/{voucher}/print', [VoucherPrintController::class, 'print'])
        ->name('petty-cash.vouchers.print');
});
