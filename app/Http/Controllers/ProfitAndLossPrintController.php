<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\IncomeStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfitAndLossPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, IncomeStatementService $service): View
    {
        $businessId = $this->companyInformationService->resolveBusinessId($request->integer('business_id') ?: null);
        $start = Carbon::parse((string) $request->query('startDate', now()->startOfYear()->toDateString()));
        $end = Carbon::parse((string) $request->query('endDate', now()->toDateString()));
        $compareStart = $request->filled('compareStartDate') ? Carbon::parse((string) $request->query('compareStartDate')) : null;
        $compareEnd = $request->filled('compareEndDate') ? Carbon::parse((string) $request->query('compareEndDate')) : null;
        $scheduleId = $request->integer('scheduleId') ?: null;

        if ($scheduleId !== null) {
            $rows = $service->generateFromSchedule(
                $scheduleId,
                $start,
                $end,
                $request->query('dimension1') ?: null,
                $request->query('dimension2') ?: null,
                $businessId,
            );
            $reportData = [
                'report_name' => 'Income Statement (Account Schedule)',
                'printed_at' => now()->format('Y-m-d H:i'),
                'period' => "{$start->format('Y-m-d')}..{$end->format('Y-m-d')}",
                'lines' => $rows->map(fn (array $row): array => [
                    'heading' => null,
                    'posting' => null,
                    'start_total' => null,
                    'end_total' => null,
                    'style' => collect([
                        ! empty($row['bold']) ? 'Bold' : null,
                        ! empty($row['italic']) ? 'Italic' : null,
                        ! empty($row['underline']) ? 'Underline' : null,
                    ])->filter()->implode(', '),
                    'description' => $row['description'] ?? '',
                    'indentation' => $row['indentation'] ?? 0,
                    'bold' => $row['bold'] ?? false,
                    'amount' => number_format((float) ($row['amount'] ?? 0), 2),
                    'compare_amount' => null,
                    'variance_percent' => null,
                ])->values()->all(),
                'totals' => [
                    'revenue' => number_format(0, 2),
                    'cogs' => number_format(0, 2),
                    'gross_profit' => number_format(0, 2),
                    'operating_expenses' => number_format(0, 2),
                    'operating_income' => number_format(0, 2),
                    'net_income' => number_format((float) $rows->sum('amount'), 2),
                    'compare_revenue' => number_format(0, 2),
                    'compare_gross_profit' => number_format(0, 2),
                    'compare_operating_expenses' => number_format(0, 2),
                    'compare_net_income' => number_format(0, 2),
                ],
                'is_custom' => true,
            ];
        } else {
            $report = $service->generate(
                fromDate: $start,
                toDate: $end,
                globalDimension1: $request->query('dimension1') ?: null,
                globalDimension2: $request->query('dimension2') ?: null,
                compareFrom: $compareStart,
                compareTo: $compareEnd,
                showBudget: filter_var($request->query('showBudget', '0'), FILTER_VALIDATE_BOOL),
                businessId: $businessId,
            );

            $reportData = $report->toBcFormat();
            if ($compareStart && $compareEnd) {
                $reportData['compare_period'] = "{$compareStart->format('Y-m-d')}..{$compareEnd->format('Y-m-d')}";
            }
        }

        return view('reports.profit-and-loss-print', [
            'reportData' => $reportData,
            'company' => $this->companyInformationService->getReportHeader($businessId),
        ]);
    }
}
