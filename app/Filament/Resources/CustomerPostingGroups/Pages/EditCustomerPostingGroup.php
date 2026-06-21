<?php

namespace App\Filament\Resources\CustomerPostingGroups\Pages;

use App\Filament\Resources\CustomerPostingGroups\CustomerPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerPostingGroup extends EditRecord
{
    protected static string $resource = CustomerPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
