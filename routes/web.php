<?php

use App\Http\Controllers\BalanceSheetPrintController;
use App\Http\Controllers\GroupSummaryPrintController;
use App\Http\Controllers\ProfitAndLossPrintController;
use App\Http\Controllers\WaybillController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
