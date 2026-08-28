<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\CustomerSettlementHistoryExport;
use App\Services\Finance\CustomerSettlementHistoryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerSettlementHistoryExportController extends Controller
{
    public function __invoke(Request $request, CustomerSettlementHistoryService $service): StreamedResponse|BinaryFileResponse
    {
        abort_unless($request->user()?->can('finance.customer_settlement_history.export'), 403);

        $filters = $request->only([
            'customer_id',
            'settlement_type',
            'source_document_number',
            'target_document_number',
            'date_from',
            'date_to',
            'currency_code',
            'business_id',
        ]);

        if ((string) $request->query('format') === 'xlsx') {
            return Excel::download(new CustomerSettlementHistoryExport($filters), 'customer-settlement-history.xlsx');
        }

        return response()->streamDownload(function () use ($service, $filters): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Settlement Date',
                'Customer Number',
                'Customer Name',
                'Settlement Type',
                'Source Document Type',
                'Source Document Number',
                'Source Document Date',
                'Target Document Type',
                'Target Document Number',
                'Target Document Date',
                'Original Invoice',
                'Amount Applied',
                'Currency',
                'Source Remaining Before',
                'Source Remaining After',
                'Target Remaining Before',
                'Target Remaining After',
                'Applied By',
                'Source Ledger Entry',
                'Target Ledger Entry',
                'Application/Trace ID',
                'Idempotency/Reference Key',
                'Business ID',
            ]);

            foreach ($service->rows($filters) as $row) {
                fputcsv($out, [
                    (string) $row->application_date,
                    $row->customer_number,
                    $row->customer_name,
                    $row->settlement_type,
                    $row->source_document_type,
                    $row->source_document_number,
                    (string) $row->source_document_date,
                    $row->target_document_type,
                    $row->target_document_number,
                    (string) $row->target_document_date,
                    $row->original_invoice_number,
                    number_format((float) $row->amount_applied, 4, '.', ''),
                    $row->currency_code,
                    $row->source_remaining_before === null ? null : number_format((float) $row->source_remaining_before, 4, '.', ''),
                    $row->source_remaining_after === null ? null : number_format((float) $row->source_remaining_after, 4, '.', ''),
                    $row->target_remaining_before === null ? null : number_format((float) $row->target_remaining_before, 4, '.', ''),
                    $row->target_remaining_after === null ? null : number_format((float) $row->target_remaining_after, 4, '.', ''),
                    $row->applied_by_name,
                    $row->source_ledger_entry_id,
                    $row->target_ledger_entry_id,
                    $row->settlement_id,
                    $row->reference_key,
                    $row->business_id,
                ]);
            }

            fclose($out);
        }, 'customer-settlement-history.csv');
    }
}
