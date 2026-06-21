<?php

namespace App\Filament\Resources\CapExProjects\Pages;

use App\Filament\Resources\CapExProjects\CapExProjectResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCapExProject extends ViewRecord
{
    protected static string $resource = CapExProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
