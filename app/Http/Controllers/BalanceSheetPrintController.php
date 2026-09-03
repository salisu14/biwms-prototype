<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\Finance\BalanceSheetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BalanceSheetPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, BalanceSheetService $service): View
    {
        $businessId = $this->companyInformationService->resolveBusinessId($request->integer('business_id') ?: null);
        $asOfDate = Carbon::parse((string) $request->query('asOfDate', now()->toDateString()));

        $reportData = filled($request->query('scheduleId'))
            ? $service->generateFromSchedule((int) $request->query('scheduleId'), $asOfDate, $businessId)
            : $service->generate($asOfDate, $businessId);

        return view('reports.balance-sheet-print', [
            'reportData' => $reportData,
            'company' => $this->companyInformationService->getReportHeader($businessId),
        ]);
    }
}
