<?php

namespace App\Filament\Resources\ItemTrackingCodes\Pages;

use App\Filament\Resources\ItemTrackingCodes\ItemTrackingCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemTrackingCode extends EditRecord
{
    protected static string $resource = ItemTrackingCodeResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return $record->code ? "Edit {$record->code}" : 'Edit Item Tracking Code';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return $record->description ?: null;
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->code ?: 'Item Tracking Code';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
