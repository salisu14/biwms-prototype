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
        $asOfDate = Carbon::parse((string) $request->query('asOfDate', now()->toDateString()));

        $reportData = $service->generate($asOfDate);

        return view('reports.balance-sheet-print', [
            'reportData' => $reportData,
            'company' => $this->companyInformationService->getReportHeader(),
        ]);
    }
}
