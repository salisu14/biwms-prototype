<?php

namespace App\Filament\Resources\PettyCashFunds\Pages;

use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPettyCashFund extends ViewRecord
{
    protected static string $resource = PettyCashFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
