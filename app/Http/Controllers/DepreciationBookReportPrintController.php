<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\FixedAsset\DepreciationBookReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepreciationBookReportPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, DepreciationBookReportService $service): View|StreamedResponse
    {
        $reportData = $service->generate([
            'as_of_date' => $request->date('as_of_date')?->toDateString(),
            'fa_class_id' => $request->integer('fa_class_id') ?: null,
            'location_id' => $request->integer('location_id') ?: null,
            'depreciation_book_id' => $request->integer('depreciation_book_id') ?: null,
        ]);

        if ((string) $request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($reportData): void {
                $out = fopen('php://output', 'w');

                fputcsv($out, ['Depreciation Book Report']);
                fputcsv($out, ['As of Date', (string) ($reportData['as_of_date'] ?? $reportData['printed_at'])]);
                fputcsv($out, ['Asset Count', (string) $reportData['summary']['asset_count']]);
                fputcsv($out, ['Annual Depreciation', number_format((float) $reportData['summary']['annual_depreciation'], 2, '.', '')]);
                fputcsv($out, ['Accumulated Depreciation', number_format((float) $reportData['summary']['accumulated_depreciation'], 2, '.', '')]);
                fputcsv($out, ['Remaining Book Value', number_format((float) $reportData['summary']['remaining_book_value'], 2, '.', '')]);
                fputcsv($out, []);
                fputcsv($out, ['Asset', 'Book Code', 'Method', 'Class', 'Location', 'Annual Depreciation', 'Accumulated Depreciation', 'Remaining Book Value', 'Next Depreciation Date']);

                foreach ($reportData['rows'] as $row) {
                    fputcsv($out, [
                        trim($row['fa_no'].' - '.$row['description'], ' -'),
                        $row['book_code'],
                        $row['method'],
                        $row['class'],
                        $row['location'],
                        number_format((float) $row['annual_depreciation'], 2, '.', ''),
                        number_format((float) $row['accumulated_depreciation'], 2, '.', ''),
                        number_format((float) $row['remaining_book_value'], 2, '.', ''),
                        $row['next_depreciation_date'],
                    ]);
                }

                fclose($out);
            }, 'depreciation-book-report-'.($reportData['as_of_date'] ?? now()->toDateString()).'.csv');
        }

        return view('reports.depreciation-book-report-print', [
            'reportData' => $reportData,
            'company' => $this->companyInformationService->getReportHeader(),
        ]);
    }
}
