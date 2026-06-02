<?php

namespace App\Filament\Resources\ItemLedgerEntries\Pages;

use App\Filament\Pages\Finance\ItemLedgerSummary;
use App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemLedgerEntries extends ListRecords
{
    protected static string $resource = ItemLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('summaryView')
                ->label('Summary View')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(ItemLedgerSummary::getUrl()),
            CreateAction::make(),
        ];
    }
}
