<?php

use App\Http\Controllers\WaybillController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/sales-shipments/{shipment}/waybill', [WaybillController::class, 'print'])
    ->name('waybill.print')
    ->middleware(['web', 'auth']);
