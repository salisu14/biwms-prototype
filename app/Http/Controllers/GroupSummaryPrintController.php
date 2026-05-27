<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Finance\GroupSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupSummaryPrintController extends Controller
{
    public function __invoke(Request $request, GroupSummaryService $service): View
    {
        $start = Carbon::parse((string) $request->query('startDate', now()->startOfYear()->toDateString()));
        $end = Carbon::parse((string) $request->query('endDate', now()->toDateString()));
        $category = $request->query('category') ?: null;
        $includeSubLedgers = filter_var($request->query('includeSubLedgers', '1'), FILTER_VALIDATE_BOOL);

        $report = $service->generate($start, $end, $category, $includeSubLedgers);

        return view('reports.group-summary-print', [
            'report' => $report,
        ]);
    }
}
