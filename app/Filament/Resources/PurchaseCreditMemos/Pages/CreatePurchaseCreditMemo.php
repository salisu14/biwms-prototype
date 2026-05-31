<?php

namespace App\Filament\Resources\PurchaseCreditMemos\Pages;

use App\Filament\Resources\PurchaseCreditMemos\PurchaseCreditMemoResource;
use App\Filament\Traits\ShowsMissingNumberSeriesWarning;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseCreditMemo extends CreateRecord
{
    use ShowsMissingNumberSeriesWarning;

    protected static string $resource = PurchaseCreditMemoResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->warnIfMissingNumberSeries(['P-CM', 'PURCHASE_CREDIT_MEMO', 'PCM'], 'Purchase Credit Memo');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['lines'] = collect($data['lines'] ?? [])
            ->filter(fn (array $line): bool => (bool) ($line['is_selected'] ?? true))
            ->map(function (array $line): array {
                unset($line['is_selected'], $line['max_credit_quantity'], $line['line_total_preview'], $line['tax_amount_preview']);

                return $line;
            })
            ->values()
            ->all();

        return $data;
    }
}
