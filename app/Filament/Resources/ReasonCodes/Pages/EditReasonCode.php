<?php

namespace App\Filament\Resources\ReasonCodes\Pages;

use App\Filament\Resources\ReasonCodes\ReasonCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReasonCode extends EditRecord
{
    protected static string $resource = ReasonCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
