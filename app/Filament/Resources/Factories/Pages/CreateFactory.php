<?php

namespace App\Filament\Resources\Factories\Pages;

use App\Filament\Resources\Factories\FactoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFactory extends CreateRecord
{
    protected static string $resource = FactoryResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $dto = \App\DTOs\FactoryDTO::fromArray($data);
        return app(\App\Services\OrgEntityService::class)->upsertFactory($dto);
    }
}
