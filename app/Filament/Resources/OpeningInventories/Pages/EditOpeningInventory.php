<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Pages;

use App\Filament\Resources\OpeningInventories\OpeningInventoryResource;
use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOpeningInventory extends EditRecord
{
    protected static string $resource = OpeningInventoryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var OpeningInventory $record */
        $record = $this->record->load('lines');
        $data['lines'] = $record->lines
            ->sortBy('line_number')
            ->map(fn ($line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'location_id' => $line->location_id,
                'unit_of_measure_id' => $line->unit_of_measure_id,
                'quantity' => $line->quantity,
                'quantity_base' => $line->quantity_base,
                'unit_cost' => $line->unit_cost,
                'amount' => $line->amount,
                'lot_number' => $line->lot_number,
                'serial_number' => $line->serial_number,
            ])
            ->values()
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines'], $data['status']);

        return app(OpeningInventoryService::class)->updateDraft($record, $data, $lines);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('delete', $record) === true),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
