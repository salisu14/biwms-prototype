<?php

namespace App\Filament\Resources\GeneralPostingSetups\Pages;

use App\Filament\Resources\GeneralPostingSetups\GeneralPostingSetupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGeneralPostingSetup extends CreateRecord
{
    protected static string $resource = GeneralPostingSetupResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'unique_posting_setup')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'general_product_posting_group_id' => 'This posting setup already exists for the selected combination.',
                ]);
            }

            throw $e;
        }
    }
}
