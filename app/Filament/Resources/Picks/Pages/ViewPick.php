<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\Pages;

use App\Filament\Resources\Picks\PickResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPick extends ViewRecord
{
    protected static string $resource = PickResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
