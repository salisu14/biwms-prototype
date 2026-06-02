<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\FixedAsset\FixedAssetListReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FixedAssetListPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, FixedAssetListReportService $service): View|StreamedResponse
    {
        $reportData = $service->generate([
            'as_of_date' => $request->date('as_of_date')?->toDateString(),
            'fa_class_id' => $request->integer('fa_class_id') ?: null,
            'location_id' => $request->integer('location_id') ?: null,
            'depreciation_book_id' => $request->integer('depreciation_book_id') ?: null,
            'status' => $request->filled('status') ? (string) $request->query('status') : null,
        ]);

        if ((string) $request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($reportData): void {
                $out = fopen('php://output', 'w');

                fputcsv($out, ['Fixed Asset List']);
                fputcsv($out, ['As of Date', (string) ($reportData['as_of_date'] ?? $reportData['printed_at'])]);
                fputcsv($out, ['Asset Count', (string) $reportData['summary']['asset_count']]);
                fputcsv($out, ['Acquisition Cost', number_format((float) $reportData['summary']['acquisition_cost'], 2, '.', '')]);
                fputcsv($out, ['Accumulated Depreciation', number_format((float) $reportData['summary']['accumulated_depreciation'], 2, '.', '')]);
                fputcsv($out, ['Net Book Value', number_format((float) $reportData['summary']['net_book_value'], 2, '.', '')]);
                fputcsv($out, []);
                fputcsv($out, ['Asset No.', 'Description', 'Class', 'Location', 'Acquisition Date', 'Acquisition Cost', 'Accumulated Depreciation', 'Net Book Value', 'Method', 'Life Remaining', 'Status']);

                foreach ($reportData['rows'] as $row) {
                    fputcsv($out, [
                        $row['fa_no'],
                        $row['description'],
                        $row['class'],
                        $row['location'],
                        $row['acquisition_date'],
                        number_format((float) $row['acquisition_cost'], 2, '.', ''),
                        number_format((float) $row['accumulated_depreciation'], 2, '.', ''),
                        number_format((float) $row['net_book_value'], 2, '.', ''),
                        $row['depreciation_method'],
                        $row['useful_life_remaining_label'],
                        $row['status'],
                    ]);
                }

                fclose($out);
            }, 'fixed-asset-list-'.($reportData['as_of_date'] ?? now()->toDateString()).'.csv');
        }

        return view('reports.fixed-asset-list-print', [
            'reportData' => $reportData,
            'company' => $this->companyInformationService->getReportHeader(),
        ]);
    }
}
