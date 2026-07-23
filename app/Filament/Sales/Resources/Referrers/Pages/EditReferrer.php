<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\Referrers\Pages;

use App\Filament\Sales\Resources\Referrers\ReferrerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReferrer extends EditRecord
{
    protected static string $resource = ReferrerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
