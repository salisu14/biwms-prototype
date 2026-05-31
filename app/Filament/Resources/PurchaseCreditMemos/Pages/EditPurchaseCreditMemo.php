<?php

namespace App\Filament\Resources\PurchaseCreditMemos\Pages;

use App\Filament\Resources\PurchaseCreditMemos\PurchaseCreditMemoResource;
use App\Filament\Traits\ShowsMissingApprovalTemplateWarning;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseCreditMemo extends EditRecord
{
    use ShowsMissingApprovalTemplateWarning;

    protected static string $resource = PurchaseCreditMemoResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $this->warnIfMissingApprovalTemplate($this->record, 'Purchase Credit Memo');
    }
}
