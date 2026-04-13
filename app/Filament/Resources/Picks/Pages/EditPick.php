<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\Pages;

use App\Filament\Resources\Picks\PickResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPick extends EditRecord
{
    protected static string $resource = PickResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
