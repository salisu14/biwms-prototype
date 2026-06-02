<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Company\CompanyInformationService;
use App\Services\Customer\CustomerSubledgerSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerSubledgerSummaryPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, CustomerSubledgerSummaryService $service): View|StreamedResponse
    {
        $customerId = $request->integer('customerId') ?: null;
        $documentTypeFilter = $request->filled('documentTypeFilter') ? (string) $request->query('documentTypeFilter') : null;
        $monthFilter = $request->filled('monthFilter') ? (string) $request->query('monthFilter') : null;

        $report = $service->generate([
            'customer_id' => $customerId,
            'document_type' => $documentTypeFilter,
            'month_filter' => $monthFilter,
        ]);

        if ((string) $request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($report, $customerId, $documentTypeFilter, $monthFilter): void {
                $out = fopen('php://output', 'w');

                $customerLabel = $customerId !== null
                    ? Customer::query()->whereKey($customerId)->value('name') ?? 'Selected Customer'
                    : 'All Customers';

                fputcsv($out, ['Customer Subledger Summary']);
                fputcsv($out, ['Customer', $customerLabel]);
                fputcsv($out, ['Document Type Filter', $documentTypeFilter ?? 'All']);
                fputcsv($out, ['Month Filter', $monthFilter ?? 'All']);
                fputcsv($out, []);
                fputcsv($out, ['Aging Snapshot']);
                fputcsv($out, ['Current', number_format((float) ($report['aging']['current'] ?? 0), 2, '.', '')]);
                fputcsv($out, ['1-30 Days', number_format((float) ($report['aging']['1_30'] ?? 0), 2, '.', '')]);
                fputcsv($out, ['31-60 Days', number_format((float) ($report['aging']['31_60'] ?? 0), 2, '.', '')]);
                fputcsv($out, ['61-90 Days', number_format((float) ($report['aging']['61_90'] ?? 0), 2, '.', '')]);
                fputcsv($out, ['Over 90 Days', number_format((float) ($report['aging']['over_90'] ?? 0), 2, '.', '')]);
                fputcsv($out, []);
                fputcsv($out, ['Posting Date', 'Customer', 'Document Type', 'Document No.', 'Description', 'Debit', 'Credit', 'Balance', 'Remaining']);

                foreach ($report['entries'] as $entry) {
                    fputcsv($out, [
                        optional($entry->posting_date)?->toDateString(),
                        $entry->customer?->name,
                        $entry->document_type,
                        $entry->document_number,
                        $entry->description,
                        number_format((float) $entry->debit_amount, 2, '.', ''),
                        number_format((float) $entry->credit_amount, 2, '.', ''),
                        number_format((float) $entry->running_balance, 2, '.', ''),
                        number_format((float) $entry->remaining_amount, 2, '.', ''),
                    ]);
                }

                fclose($out);
            }, 'customer-subledger-summary-'.($customerId ?? 'all').'.csv');
        }

        return view('reports.customer-subledger-summary-print', [
            'company' => $this->companyInformationService->getReportHeader(),
            'report' => $report,
            'customer' => $report['customer'] ?? null,
            'documentTypeFilter' => $documentTypeFilter,
            'monthFilter' => $monthFilter,
        ]);
    }
}
