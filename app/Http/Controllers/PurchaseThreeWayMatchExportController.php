<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\Purchase\PurchaseThreeWayMatchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseThreeWayMatchExportController extends Controller
{
    public function __invoke(Request $request, PurchaseThreeWayMatchService $service, CompanyInformationService $companyInformationService): StreamedResponse|Response
    {
        abort_unless($request->user()?->can('purchasing.purchase_three_way_match.export'), 403);

        $businessId = $companyInformationService->resolveBusinessId(
            filled($request->query('business_id')) ? (int) $request->query('business_id') : null,
        );

        $filters = $request->only([
            'vendor_id',
            'purchase_order_id',
            'match_status',
            'date_from',
            'date_to',
        ]) + ['business_id' => $businessId];

        $report = $service->generate($filters);

        if ((string) $request->query('format') === 'pdf') {
            return Pdf::loadView('pdf.purchase-three-way-match', [
                'report' => $report,
                'generatedAt' => now(),
                'company' => $companyInformationService->getReportHeader($businessId),
            ])
                ->setPaper('a4', 'landscape')
                ->download('purchase-three-way-match.pdf');
        }

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Reference Type',
                'Reference Number',
                'Vendor Number',
                'Vendor Name',
                'Item Code',
                'Description',
                'UOM',
                'Line Number',
                'Ordered Quantity',
                'Received Quantity',
                'Invoiced Quantity',
                'Remaining To Receive',
                'Remaining To Invoice',
                'PO Unit Cost',
                'Received Value',
                'Invoice Unit Cost',
                'Invoiced Value',
                'Quantity Variance',
                'Price Variance',
                'Amount Variance',
                'Match Status',
                'Receipt Document Number',
                'Invoice Document Number',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row->reference_type,
                    $row->reference_number,
                    $row->vendor_number,
                    $row->vendor_name,
                    $row->item_code,
                    $row->description,
                    $row->unit_of_measure_code,
                    $row->line_number,
                    $row->ordered_quantity === null ? null : number_format((float) $row->ordered_quantity, 4, '.', ''),
                    $row->received_quantity === null ? null : number_format((float) $row->received_quantity, 4, '.', ''),
                    $row->invoiced_quantity === null ? null : number_format((float) $row->invoiced_quantity, 4, '.', ''),
                    $row->remaining_to_receive === null ? null : number_format((float) $row->remaining_to_receive, 4, '.', ''),
                    $row->remaining_to_invoice === null ? null : number_format((float) $row->remaining_to_invoice, 4, '.', ''),
                    $row->po_unit_cost === null ? null : number_format((float) $row->po_unit_cost, 4, '.', ''),
                    $row->received_value === null ? null : number_format((float) $row->received_value, 4, '.', ''),
                    $row->invoice_unit_cost === null ? null : number_format((float) $row->invoice_unit_cost, 4, '.', ''),
                    $row->invoiced_value === null ? null : number_format((float) $row->invoiced_value, 4, '.', ''),
                    $row->quantity_variance === null ? null : number_format((float) $row->quantity_variance, 4, '.', ''),
                    $row->price_variance === null ? null : number_format((float) $row->price_variance, 4, '.', ''),
                    $row->amount_variance === null ? null : number_format((float) $row->amount_variance, 4, '.', ''),
                    $row->match_status,
                    $row->receipt_document_number,
                    $row->invoice_document_number,
                ]);
            }

            fclose($out);
        }, 'purchase-three-way-match.csv');
    }
}
