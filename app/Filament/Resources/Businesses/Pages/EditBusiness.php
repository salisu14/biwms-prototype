<?php

namespace App\Filament\Resources\Businesses\Pages;

use App\DTOs\BusinessDTO;
use App\Filament\Resources\Businesses\BusinessResource;
use App\Services\OrgEntityService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBusiness extends EditRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $dto = BusinessDTO::fromArray($data);

        return app(OrgEntityService::class)->upsertBusiness($dto, $record);
    }
}
