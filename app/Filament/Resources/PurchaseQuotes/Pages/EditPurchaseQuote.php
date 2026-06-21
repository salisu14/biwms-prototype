<?php

namespace App\Filament\Resources\PurchaseQuotes\Pages;

use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use App\Filament\Shared\Actions\ApprovalActions;
use App\Filament\Traits\ShowsMissingApprovalTemplateWarning;
use App\Filament\Traits\ShowsMissingNumberSeriesWarning;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseQuote extends EditRecord
{
    use ShowsMissingApprovalTemplateWarning;
    use ShowsMissingNumberSeriesWarning;

    protected static string $resource = PurchaseQuoteResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $this->warnIfMissingApprovalTemplate($this->record, 'Purchase Quote');
        $this->warnIfMissingNumberSeries(['P-QUOTE', 'PURCHASE_QUOTE', 'PQ'], 'Purchase Quote');
    }

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->document_no ?? 'Purchase Quote')
            .' • Scope '.($record->vendor?->vendor_code ?? '—')
            .' • Attribute '.number_format((float) $record->amount_including_vat, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->vendor?->vendor_name ?? 'Unknown Vendor')
            .' • '.($record->buyer?->name ?? 'Unknown Buyer')
            .' • '.($record->currency_code ?? 'USD');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->document_no ?? 'Purchase Quote';
    }

    protected function getHeaderActions(): array
    {
        return [
            ...ApprovalActions::all(),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
