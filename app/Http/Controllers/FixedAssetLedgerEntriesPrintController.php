<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FALedgerEntry;
use App\Models\FixedAsset;
use App\Services\Company\CompanyInformationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FixedAssetLedgerEntriesPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request): View|StreamedResponse
    {
        $fixedAssetId = $request->integer('fixedAssetId') ?: null;
        $asOfDate = $request->date('asOfDate')?->toDateString();
        $typeFilter = $request->filled('typeFilter') ? (string) $request->query('typeFilter') : null;
        $monthFilter = $request->filled('monthFilter') ? (string) $request->query('monthFilter') : null;

        $asset = $fixedAssetId !== null
            ? FixedAsset::query()->with(['faClass', 'location', 'depreciationBook'])->find($fixedAssetId)
            : null;

        $entries = FALedgerEntry::query()
            ->with(['depreciationBook'])
            ->when(
                $fixedAssetId !== null,
                fn ($query) => $query->where('fixed_asset_id', $fixedAssetId)
            )
            ->when(
                $asOfDate !== null,
                fn ($query) => $query->whereDate('posting_date', '<=', $asOfDate)
            )
            ->when(
                $typeFilter !== null,
                fn ($query) => $query->where('fa_posting_type', $typeFilter)
            )
            ->when(
                $monthFilter !== null,
                fn ($query) => $query->whereRaw("to_char(posting_date, 'YYYY-MM') = ?", [$monthFilter])
            )
            ->orderByDesc('posting_date')
            ->orderByDesc('entry_no')
            ->get();

        if ((string) $request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($entries, $asset, $asOfDate, $typeFilter, $monthFilter): void {
                $out = fopen('php://output', 'w');

                fputcsv($out, ['Fixed Asset Ledger Entries']);
                fputcsv($out, ['Asset', $asset !== null ? $asset->fa_no.' - '.$asset->description : 'All Assets']);
                fputcsv($out, ['As of Date', (string) ($asOfDate ?? 'Full history')]);
                fputcsv($out, ['Type Filter', (string) ($typeFilter ?? 'All')]);
                fputcsv($out, ['Month Filter', (string) ($monthFilter ?? 'All')]);
                fputcsv($out, []);
                fputcsv($out, ['Posting Date', 'Entry No.', 'Type', 'Document No.', 'Book', 'Amount', 'Depreciation', 'Accumulated Depreciation', 'Book Value After', 'Description']);

                foreach ($entries as $entry) {
                    fputcsv($out, [
                        optional($entry->posting_date)?->toDateString(),
                        $entry->entry_no,
                        $entry->fa_posting_type,
                        $entry->document_no,
                        $entry->depreciationBook?->code,
                        number_format((float) $entry->amount, 2, '.', ''),
                        number_format((float) $entry->depreciation_amount, 2, '.', ''),
                        number_format((float) $entry->accumulated_depreciation, 2, '.', ''),
                        number_format((float) $entry->book_value_after, 2, '.', ''),
                        $entry->description,
                    ]);
                }

                fclose($out);
            }, 'fixed-asset-ledger-'.($asset?->fa_no ?? 'all').'-'.($asOfDate ?? now()->toDateString()).'.csv');
        }

        return view('reports.fixed-asset-ledger-entries-print', [
            'company' => $this->companyInformationService->getReportHeader(),
            'asset' => $asset,
            'entries' => $entries,
            'asOfDate' => $asOfDate,
            'typeFilter' => $typeFilter,
            'monthFilter' => $monthFilter,
        ]);
    }
}
