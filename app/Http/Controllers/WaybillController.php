<?php

namespace App\Http\Controllers;

use App\Models\SalesShipmentHeader;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;

class WaybillController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function print(SalesShipmentHeader $shipment)
    {
        $shipment->load(['lines', 'customer']);
        $header = $this->companyInformationService->getReportHeader();

        $pdf = Pdf::loadView('pdf.waybill', [
            'shipment' => $shipment,
            'copies' => ['Store Copy', 'Gate Pass', 'Driver Copy'],
            'company' => [
                'name' => $header['name'] ?? config('app.name'),
                'address_lines' => $header['address_lines'] ?? [],
                'phone' => $header['phone'] ?? null,
                'email' => $header['email'] ?? null,
                'website' => $header['website'] ?? null,
                'logo_url' => $header['logo_url'] ?? null,
            ],
        ]);

        return $pdf->stream('waybill-'.$shipment->document_no.'.pdf');
    }
}
