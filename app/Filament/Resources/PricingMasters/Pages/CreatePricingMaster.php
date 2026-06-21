<?php

namespace App\Filament\Resources\PricingMasters\Pages;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePricingMaster extends CreateRecord
{
    protected static string $resource = PricingMasterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = (string) auth()->id();

        $data['approved_by'] = $data['approved_by'] ?? $userId;
        $data['approved_at'] = $data['approved_at'] ?? now();
        $data['created_by'] = $data['created_by'] ?? $userId;
        $data['modified_by'] = $data['modified_by'] ?? $userId;

        return $data;
    }
}
