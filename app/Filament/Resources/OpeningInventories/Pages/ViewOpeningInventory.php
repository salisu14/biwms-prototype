<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Pages;

use App\Filament\Resources\OpeningInventories\OpeningInventoryResource;
use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOpeningInventory extends ViewRecord
{
    protected static string $resource = OpeningInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('update', $record) === true),
            SensitiveActionPasswordConfirmation::protect(
                Action::make('post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('post', $record) === true)
                    ->action(function (OpeningInventory $record): void {
                        app(OpeningInventoryService::class)->post($record, auth()->id());
                        Notification::make()->title('Opening inventory posted')->success()->send();
                    })
            ),
            SensitiveActionPasswordConfirmation::protect(
                Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('cancel', $record) === true)
                    ->action(function (OpeningInventory $record): void {
                        app(OpeningInventoryService::class)->cancelDraft($record, auth()->id());
                        Notification::make()->title('Opening inventory cancelled')->success()->send();
                    })
            ),
        ];
    }
}
