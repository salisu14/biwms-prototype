<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Services\Company\CompanyInformationService;
use App\Services\Inventory\ItemLedgerSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemLedgerSummaryPrintController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function __invoke(Request $request, ItemLedgerSummaryService $service): View|StreamedResponse
    {
        $entryTypeFilter = $request->filled('entryTypeFilter') ? (string) $request->query('entryTypeFilter') : null;
        $monthFilter = $request->filled('monthFilter') ? (string) $request->query('monthFilter') : null;
        $itemId = $request->integer('itemId') ?: null;
        $locationId = $request->integer('locationId') ?: null;

        $report = $service->generate([
            'entry_type' => $entryTypeFilter,
            'month_filter' => $monthFilter,
            'item_id' => $itemId,
            'location_id' => $locationId,
        ]);

        if ((string) $request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($report, $entryTypeFilter, $monthFilter, $itemId, $locationId): void {
                $out = fopen('php://output', 'w');

                $itemLabel = $itemId !== null
                    ? Item::query()->whereKey($itemId)->value('description') ?? 'Selected Item'
                    : 'All Items';
                $locationLabel = $locationId !== null
                    ? Location::query()->whereKey($locationId)->value('name') ?? 'Selected Location'
                    : 'All Locations';

                fputcsv($out, ['Item Ledger Summary']);
                fputcsv($out, ['Entry Type Filter', $entryTypeFilter ?? 'All']);
                fputcsv($out, ['Month Filter', $monthFilter ?? 'All']);
                fputcsv($out, ['Item Filter', $itemLabel]);
                fputcsv($out, ['Location Filter', $locationLabel]);
                fputcsv($out, []);
                fputcsv($out, ['Posting Date', 'Entry No.', 'Type', 'Document No.', 'Item', 'Location', 'Quantity', 'Remaining Qty', 'Cost']);

                foreach ($report['entries'] as $entry) {
                    fputcsv($out, [
                        optional($entry->posting_date)?->toDateString(),
                        $entry->entry_number,
                        $entry->entry_type->value,
                        $entry->document_number,
                        trim(($entry->item?->item_code ?? '').' - '.($entry->item?->description ?? ''), ' -'),
                        $entry->location?->name,
                        number_format((float) $entry->quantity, 2, '.', ''),
                        number_format((float) $entry->remaining_quantity, 2, '.', ''),
                        number_format((float) $entry->cost_amount_actual, 2, '.', ''),
                    ]);
                }

                fclose($out);
            }, 'item-ledger-summary-'.($itemId ?? 'all').'-'.($locationId ?? 'all').'.csv');
        }

        return view('reports.item-ledger-summary-print', [
            'company' => $this->companyInformationService->getReportHeader(),
            'report' => $report,
            'entryTypeFilter' => $entryTypeFilter,
            'monthFilter' => $monthFilter,
            'item' => $itemId !== null ? Item::query()->find($itemId) : null,
            'location' => $locationId !== null ? Location::query()->find($locationId) : null,
        ]);
    }
}
