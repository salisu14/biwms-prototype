<?php

namespace App\Filament\Resources\ItemTrackingCodes\Pages;

use App\Filament\Resources\ItemTrackingCodes\ItemTrackingCodeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemTrackingCode extends ViewRecord
{
    protected static string $resource = ItemTrackingCodeResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return $record->code ?: 'Item Tracking Code';
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
            EditAction::make(),
        ];
    }
}
