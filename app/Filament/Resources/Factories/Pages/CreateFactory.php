<?php

namespace App\Filament\Resources\Factories\Pages;

use App\DTOs\FactoryDTO;
use App\Filament\Resources\Factories\FactoryResource;
use App\Services\OrgEntityService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFactory extends CreateRecord
{
    protected static string $resource = FactoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $dto = FactoryDTO::fromArray($data);

        return app(OrgEntityService::class)->upsertFactory($dto);
    }
}
