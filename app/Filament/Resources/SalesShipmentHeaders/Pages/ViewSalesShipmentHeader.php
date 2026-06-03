<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Pages;

use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesShipmentHeader extends ViewRecord
{
    protected static string $resource = SalesShipmentHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printWaybill')
                ->label('Print Waybill')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn ($record) => route('waybill.print', $record))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
