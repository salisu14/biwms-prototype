<?php

namespace App\Filament\Resources\PurchaseQuotes\Pages;

use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use App\Filament\Shared\Actions\ApprovalActions;
use App\Filament\Traits\ShowsMissingApprovalTemplateWarning;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseQuote extends EditRecord
{
    use ShowsMissingApprovalTemplateWarning;

    protected static string $resource = PurchaseQuoteResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $this->warnIfMissingApprovalTemplate($this->record, 'Purchase Quote');
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
