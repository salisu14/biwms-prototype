<?php

namespace App\Filament\Resources\Factories\Pages;

use App\DTOs\FactoryDTO;
use App\Filament\Resources\Factories\FactoryResource;
use App\Services\OrgEntityService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFactory extends EditRecord
{
    protected static string $resource = FactoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $dto = FactoryDTO::fromArray($data);

        return app(OrgEntityService::class)->upsertFactory($dto, $record);
    }
}
