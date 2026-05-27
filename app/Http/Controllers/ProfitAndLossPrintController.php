<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\IncomeStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfitAndLossPrintController extends Controller
{
    public function __invoke(Request $request, IncomeStatementService $service): View
    {
        $start = Carbon::parse((string) $request->query('startDate', now()->startOfYear()->toDateString()));
        $end = Carbon::parse((string) $request->query('endDate', now()->toDateString()));
        $compareStart = $request->filled('compareStartDate') ? Carbon::parse((string) $request->query('compareStartDate')) : null;
        $compareEnd = $request->filled('compareEndDate') ? Carbon::parse((string) $request->query('compareEndDate')) : null;

        $report = $service->generate(
            fromDate: $start,
            toDate: $end,
            globalDimension1: $request->query('dimension1') ?: null,
            globalDimension2: $request->query('dimension2') ?: null,
            compareFrom: $compareStart,
            compareTo: $compareEnd,
            showBudget: filter_var($request->query('showBudget', '0'), FILTER_VALIDATE_BOOL),
        );

        $reportData = $report->toBcFormat();
        if ($compareStart && $compareEnd) {
            $reportData['compare_period'] = "{$compareStart->format('Y-m-d')}..{$compareEnd->format('Y-m-d')}";
        }

        return view('reports.profit-and-loss-print', [
            'reportData' => $reportData,
        ]);
    }
}
