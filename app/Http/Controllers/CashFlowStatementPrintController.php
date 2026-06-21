<?php

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\Finance\CashFlowStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowStatementPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, CashFlowStatementService $service): View|StreamedResponse
    {
        $startDate = Carbon::parse((string) $request->query('startDate', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse((string) $request->query('endDate', now()->toDateString()))->endOfDay();
        $compareStartDate = $request->filled('compareStartDate')
            ? Carbon::parse((string) $request->query('compareStartDate'))->startOfDay()
            : null;
        $compareEndDate = $request->filled('compareEndDate')
            ? Carbon::parse((string) $request->query('compareEndDate'))->endOfDay()
            : null;
        $method = (string) $request->query('method', 'indirect');
        $cashFlowScheduleId = filled($request->query('cashFlowScheduleId'))
            ? (int) $request->query('cashFlowScheduleId')
            : null;
        $profitAndLossScheduleId = filled($request->query('profitAndLossScheduleId'))
            ? (int) $request->query('profitAndLossScheduleId')
            : null;
        $balanceSheetScheduleId = filled($request->query('balanceSheetScheduleId'))
            ? (int) $request->query('balanceSheetScheduleId')
            : null;
        $reportData = $service->generateComparison(
            $startDate,
            $endDate,
            $compareStartDate,
            $compareEndDate,
            $method,
            $cashFlowScheduleId,
            $profitAndLossScheduleId,
            $balanceSheetScheduleId
        );

        if ((string) $request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($reportData): void {
                $out = fopen('php://output', 'w');

                fputcsv($out, ['Cash Flow Statement']);
                fputcsv($out, ['Method', ucfirst((string) $reportData['method'])]);
                fputcsv($out, ['Period', $reportData['period']['start'].'..'.$reportData['period']['end']]);
                if (isset($reportData['compare_period'])) {
                    fputcsv($out, ['Comparison Period', $reportData['compare_period']['start'].'..'.$reportData['compare_period']['end']]);
                }
                fputcsv($out, ['Mapping Mode', (string) ($reportData['mapping']['mode'] ?? 'chart_of_accounts')]);
                fputcsv($out, ['Cash Flow Schedule', (string) ($reportData['mapping']['cash_flow_schedule'] ?? '')]);
                fputcsv($out, ['P&L Schedule', (string) ($reportData['mapping']['profit_and_loss_schedule'] ?? '')]);
                fputcsv($out, ['Balance Sheet Schedule', (string) ($reportData['mapping']['balance_sheet_schedule'] ?? '')]);
                fputcsv($out, ['Opening Cash', number_format((float) $reportData['opening_cash'], 2, '.', '')]);
                fputcsv($out, ['Net Change in Cash', number_format((float) $reportData['net_change_in_cash'], 2, '.', '')]);
                fputcsv($out, ['Ending Cash', number_format((float) $reportData['ending_cash'], 2, '.', '')]);
                fputcsv($out, []);
                fputcsv($out, ['Section', 'Description', 'Amount', 'Prior Period', 'Variance', 'Variance %']);

                foreach ($reportData['sections'] as $section) {
                    foreach ($section['lines'] as $line) {
                        fputcsv($out, [
                            $section['label'],
                            $line['label'],
                            number_format((float) $line['amount'], 2, '.', ''),
                            number_format((float) ($line['compare_amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($line['variance_amount'] ?? 0), 2, '.', ''),
                            ($line['variance_percent'] ?? null) !== null ? number_format((float) $line['variance_percent'], 2, '.', '') : '',
                        ]);
                    }

                    fputcsv($out, [
                        $section['label'],
                        'Net Cash from '.$section['label'],
                        number_format((float) $section['total'], 2, '.', ''),
                        number_format((float) ($section['compare_total'] ?? 0), 2, '.', ''),
                        number_format((float) ($section['variance_amount'] ?? 0), 2, '.', ''),
                        ($section['variance_percent'] ?? null) !== null ? number_format((float) $section['variance_percent'], 2, '.', '') : '',
                    ]);
                }

                fclose($out);
            }, 'cash-flow-statement-'.$reportData['period']['start'].'-'.$reportData['period']['end'].'.csv');
        }

        return view('reports.cash-flow-statement-print', [
            'reportData' => $reportData,
            'company' => $this->companyInformationService->getReportHeader(),
        ]);
    }
}
