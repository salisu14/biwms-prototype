<?php

namespace App\Filament\Resources\PayCodes\Pages;

use App\Filament\Resources\PayCodes\PayCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayCode extends EditRecord
{
    protected static string $resource = PayCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
