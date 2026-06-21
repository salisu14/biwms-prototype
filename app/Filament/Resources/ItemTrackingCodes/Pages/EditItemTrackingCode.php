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
        $trackingScope = $record->snspecific_tracking && $record->lotspecific_tracking
            ? 'Serial + Lot'
            : ($record->snspecific_tracking ? 'Serial' : ($record->lotspecific_tracking ? 'Lot' : 'Tracking'));

        return ($record->code ?: 'Item Tracking Code')
            .' • Scope '.($record->description ?: '—')
            .' • Attribute '.$trackingScope;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return 'Serial '.($record->snspecific_tracking ? 'Yes' : 'No')
            .' • Lot '.($record->lotspecific_tracking ? 'Yes' : 'No')
            .' • Expiration '.($record->strict_expiration_posting ? 'Strict' : 'Flexible');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return ($record->code ?: 'Item Tracking Code').($record->description ? ' - '.$record->description : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
