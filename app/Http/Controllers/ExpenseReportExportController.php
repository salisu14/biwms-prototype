<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExpenseTransaction;
use App\Services\Company\CompanyInformationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReportExportController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request): View|StreamedResponse
    {
        $period = (string) $request->query('period', 'monthly');
        $anchorDate = Carbon::parse((string) $request->query('anchorDate', now()->toDateString()));
        $categoryCode = filled($request->query('categoryCode'))
            ? (string) $request->query('categoryCode')
            : null;

        [$start, $end] = $this->resolvePeriod($period, $anchorDate);

        $query = ExpenseTransaction::query()
            ->where('status', 'posted')
            ->whereBetween('posting_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('posting_date')
            ->orderBy('document_no');

        if ($categoryCode !== null) {
            $query->where('category_code', $categoryCode);
        }

        $transactions = $query->get(['document_no', 'posting_date', 'category_code', 'expense_type', 'amount', 'vat_amount', 'status']);

        $report = [
            'period' => [
                'mode' => $period,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'category_code' => $categoryCode,
            ],
            'summary' => [
                'total_amount' => (float) $transactions->sum('amount'),
                'total_vat' => (float) $transactions->sum('vat_amount'),
                'count' => $transactions->count(),
            ],
            'rows' => $transactions,
        ];

        if ((string) $request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($report): void {
                $out = fopen('php://output', 'w');

                fputcsv($out, ['Period', ucfirst($report['period']['mode']), $report['period']['start'].'..'.$report['period']['end']]);
                fputcsv($out, ['Total Amount', number_format((float) $report['summary']['total_amount'], 2, '.', '')]);
                fputcsv($out, ['Total VAT', number_format((float) $report['summary']['total_vat'], 2, '.', '')]);
                fputcsv($out, ['Count', (string) $report['summary']['count']]);
                fputcsv($out, []);
                fputcsv($out, ['Document No', 'Posting Date', 'Category', 'Type', 'Amount', 'VAT', 'Status']);

                foreach ($report['rows'] as $row) {
                    fputcsv($out, [
                        $row->document_no,
                        optional($row->posting_date)?->toDateString(),
                        $row->category_code,
                        $row->expense_type,
                        number_format((float) $row->amount, 2, '.', ''),
                        number_format((float) $row->vat_amount, 2, '.', ''),
                        $row->status,
                    ]);
                }

                fclose($out);
            }, 'expense-report-'.$report['period']['mode'].'-'.$report['period']['start'].'-'.$report['period']['end'].'.csv');
        }

        return view('reports.expense-report-print', [
            'report' => $report,
            'company' => $this->companyInformationService->getReportHeader(),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriod(string $period, Carbon $anchor): array
    {
        return match ($period) {
            'daily' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'weekly' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
        };
    }
}
