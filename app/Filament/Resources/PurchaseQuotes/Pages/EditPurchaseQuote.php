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
