<?php

namespace App\Http\Controllers;

use App\Models\SalesShipmentHeader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class WaybillController extends Controller
{
    public function print(SalesShipmentHeader $shipment)
    {
        $shipment->load(['lines', 'customer']);

        $pdf = Pdf::loadView('pdf.waybill', [
            'shipment' => $shipment,
            'copies' => ['Store Copy', 'Gate Pass', 'Driver Copy']
        ]);

        return $pdf->stream('waybill-' . $shipment->document_no . '.pdf');
    }
}
