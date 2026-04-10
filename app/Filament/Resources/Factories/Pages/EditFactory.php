<?php

namespace App\Filament\Resources\Factories\Pages;

use App\Filament\Resources\Factories\FactoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFactory extends EditRecord
{
    protected static string $resource = FactoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $dto = \App\DTOs\FactoryDTO::fromArray($data);
        return app(\App\Services\OrgEntityService::class)->upsertFactory($dto, $record);
    }
}
