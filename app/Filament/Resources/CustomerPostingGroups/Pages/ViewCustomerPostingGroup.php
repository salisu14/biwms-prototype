<?php

namespace App\Filament\Resources\CustomerPostingGroups\Pages;

use App\Filament\Resources\CustomerPostingGroups\CustomerPostingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerPostingGroup extends ViewRecord
{
    protected static string $resource = CustomerPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
