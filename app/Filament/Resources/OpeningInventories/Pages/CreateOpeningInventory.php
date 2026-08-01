<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Pages;

use App\Filament\Resources\OpeningInventories\OpeningInventoryResource;
use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOpeningInventory extends CreateRecord
{
    protected static string $resource = OpeningInventoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines'], $data['status']);

        return app(OpeningInventoryService::class)->createDraft(
            documentNumber: (string) $data['document_number'],
            source: (string) ($data['source'] ?? 'MANUAL'),
            postingDate: $data['posting_date'] ?? now()->toDateString(),
            lines: $lines,
            businessId: isset($data['business_id']) ? (int) $data['business_id'] : null,
            createdBy: auth()->id(),
            description: $data['description'] ?? null,
        );
    }

    protected function getRedirectUrl(): string
    {
        /** @var OpeningInventory $record */
        $record = $this->record;

        return static::getResource()::getUrl('view', ['record' => $record]);
    }
}
