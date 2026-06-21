<?php

namespace App\Filament\Resources\ChartOfAccounts\Pages;

use App\Filament\Resources\Bins\BinResource;
use App\Models\ChartOfAccount;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewChartOfAccount extends ViewRecord
{
    protected static string $resource = ChartOfAccount::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
