<?php

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\Finance\StatisticsReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesStatisticsReportExportController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, StatisticsReportService $statisticsReportService): View|StreamedResponse
    {
        $report = $statisticsReportService->sales($request->only([
            'date_from',
            'date_to',
            'gen_bus_posting_group_id',
        ]));

        if ((string) $request->query('format') === 'csv') {
            return $this->csv($report, 'sales-statistics');
        }

        return view('reports.statistics-report-print', [
            'company' => $this->companyInformationService->getReportHeader(),
            'report' => $report,
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function csv(array $report, string $filenamePrefix): StreamedResponse
    {
        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [$report['title']]);
            fputcsv($out, ['Period', $report['period']['date_from'].'..'.$report['period']['date_to']]);
            fputcsv($out, ['Posting Group', $report['period']['posting_group'] ?? 'All']);
            fputcsv($out, [$report['amount_label'].' Total', number_format((float) $report['summary']['total_amount'], 2, '.', '')]);
            fputcsv($out, ['Transactions', (string) $report['summary']['total_transactions']]);
            fputcsv($out, []);
            fputcsv($out, [
                'Posting Group Code',
                'Posting Group Name',
                $report['amount_label'],
                'Transactions',
                'Average',
                'Maximum',
                'Minimum',
                'Accounts Used',
                '% of Total',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['group_code'],
                    $row['group_name'],
                    number_format((float) $row['amount'], 2, '.', ''),
                    (string) $row['transaction_count'],
                    number_format((float) $row['average_amount'], 2, '.', ''),
                    number_format((float) $row['max_amount'], 2, '.', ''),
                    number_format((float) $row['min_amount'], 2, '.', ''),
                    (string) $row['accounts_used'],
                    number_format((float) $row['percentage_of_total'], 2, '.', '').'%',
                ]);
            }

            fclose($out);
        }, $filenamePrefix.'-'.$report['period']['date_from'].'-'.$report['period']['date_to'].'.csv');
    }
}
