<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Finance\CustomerSettlementHistoryService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerSettlementHistoryExport implements FromCollection, WithHeadings
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function headings(): array
    {
        return [
            'Settlement Date',
            'Customer Number',
            'Customer Name',
            'Settlement Type',
            'Source Document Type',
            'Source Document Number',
            'Source Document Date',
            'Target Document Type',
            'Target Document Number',
            'Target Document Date',
            'Original Invoice',
            'Amount Applied',
            'Currency',
            'Source Remaining Before',
            'Source Remaining After',
            'Target Remaining Before',
            'Target Remaining After',
            'Applied By',
            'Source Ledger Entry',
            'Target Ledger Entry',
            'Application/Trace ID',
            'Idempotency/Reference Key',
            'Business ID',
        ];
    }

    public function collection(): Collection
    {
        return app(CustomerSettlementHistoryService::class)
            ->rows($this->filters)
            ->map(fn (object $row): array => [
                optional($row->application_date)->toDateTimeString() ?: (string) $row->application_date,
                $row->customer_number,
                $row->customer_name,
                $row->settlement_type,
                $row->source_document_type,
                $row->source_document_number,
                optional($row->source_document_date)->toDateString() ?: (string) $row->source_document_date,
                $row->target_document_type,
                $row->target_document_number,
                optional($row->target_document_date)->toDateString() ?: (string) $row->target_document_date,
                $row->original_invoice_number,
                number_format((float) $row->amount_applied, 4, '.', ''),
                $row->currency_code,
                $this->nullableAmount($row->source_remaining_before),
                $this->nullableAmount($row->source_remaining_after),
                $this->nullableAmount($row->target_remaining_before),
                $this->nullableAmount($row->target_remaining_after),
                $row->applied_by_name,
                $row->source_ledger_entry_id,
                $row->target_ledger_entry_id,
                $row->settlement_id,
                $row->reference_key,
                $row->business_id,
            ]);
    }

    private function nullableAmount(mixed $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 4, '.', '');
    }
}
