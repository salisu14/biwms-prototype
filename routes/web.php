<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WaybillController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/sales-shipments/{shipment}/waybill', [WaybillController::class, 'print'])
    ->name('waybill.print')
    ->middleware(['web', 'auth']);
