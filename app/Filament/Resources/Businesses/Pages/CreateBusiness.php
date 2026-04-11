<?php

namespace App\Filament\Resources\Businesses\Pages;

use App\DTOs\BusinessDTO;
use App\Filament\Resources\Businesses\BusinessResource;
use App\Services\OrgEntityService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $dto = BusinessDTO::fromArray($data);

        return app(OrgEntityService::class)->upsertBusiness($dto);
    }
}
