<?php

namespace App\Filament\Resources\PettyCashFunds\Pages;

use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPettyCashFund extends EditRecord
{
    protected static string $resource = PettyCashFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
