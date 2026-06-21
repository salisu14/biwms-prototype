<?php

use App\Http\Controllers\Auth\SuperAdminTwoFactorChallengeController;
use App\Http\Controllers\Auth\SuperAdminTwoFactorSetupController;
use App\Http\Controllers\Auth\TwoFactorManagementController;
use App\Http\Controllers\Admin\UserSecurityController;
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
    Route::get('/admin/two-factor/setup', [SuperAdminTwoFactorSetupController::class, 'create'])
        ->name('admin.two-factor.setup.create');
    Route::post('/admin/two-factor/setup', [SuperAdminTwoFactorSetupController::class, 'store'])
        ->name('admin.two-factor.setup.store');
    Route::get('/admin/two-factor/challenge', [SuperAdminTwoFactorChallengeController::class, 'create'])
        ->name('admin.two-factor.challenge.create');
    Route::post('/admin/two-factor/challenge', [SuperAdminTwoFactorChallengeController::class, 'store'])
        ->name('admin.two-factor.challenge.store');
    Route::get('/admin/two-factor/manage', [TwoFactorManagementController::class, 'index'])
        ->name('admin.two-factor.manage');
    Route::post('/admin/two-factor/disable', [TwoFactorManagementController::class, 'disable'])
        ->name('admin.two-factor.disable');
    Route::post('/admin/two-factor/reset-authenticator', [TwoFactorManagementController::class, 'resetAuthenticator'])
        ->name('admin.two-factor.reset-authenticator');
    Route::post('/admin/two-factor/recovery-codes', [TwoFactorManagementController::class, 'regenerateRecoveryCodes'])
        ->name('admin.two-factor.recovery-codes.regenerate');

    Route::get('/admin/user-security', [UserSecurityController::class, 'index'])
        ->name('admin.user-security.index');
    Route::post('/admin/user-security/{user}/require-two-factor', [UserSecurityController::class, 'requireTwoFactor'])
        ->name('admin.user-security.require-two-factor');
    Route::post('/admin/user-security/{user}/disable-two-factor', [UserSecurityController::class, 'disableTwoFactor'])
        ->name('admin.user-security.disable-two-factor');
    Route::post('/admin/user-security/{user}/reset-two-factor', [UserSecurityController::class, 'resetTwoFactor'])
        ->name('admin.user-security.reset-two-factor');
    Route::post('/admin/user-security/{user}/regenerate-recovery-codes', [UserSecurityController::class, 'regenerateRecoveryCodes'])
        ->name('admin.user-security.regenerate-recovery-codes');
    Route::post('/admin/user-security/{user}/clear-two-factor-session', [UserSecurityController::class, 'clearCurrentTwoFactorSession'])
        ->name('admin.user-security.clear-two-factor-session');

    Route::redirect('/super-admin/two-factor/setup', '/admin/two-factor/setup');
    Route::redirect('/super-admin/two-factor/challenge', '/admin/two-factor/challenge');
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
