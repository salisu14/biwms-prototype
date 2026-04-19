<?php

namespace App\Filament\Resources\ProductionOrders\Actions;

use App\Enums\ProductionOrderStatus;
use App\Services\Manufacturing\ProductionOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class ProductionOrderActions
{
    public static function refresh(): Action
    {
        return Action::make('refresh')
            ->label('Refresh Lines')
            ->icon('heroicon-m-arrow-path')
            ->visible(fn ($record) => $record->status->isEditable())
            ->action(function ($record) {
                try {
                    app(ProductionOrderService::class)->refresh($record);
                    Notification::make()->title('Production Order Refreshed')->success()->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    public static function release(): Action
    {
        return Action::make('release')
            ->label('Release')
            ->icon('heroicon-m-play')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn ($record) => $record->status === ProductionOrderStatus::FIRM_PLANNED)
            ->action(function ($record) {
                try {
                    app(ProductionOrderService::class)->release($record, auth()->id());
                    Notification::make()->title('Production Order Released')->success()->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    public static function postOutput(): Action
    {
        return Action::make('postOutput')
            ->label('Post Output')
            ->icon('heroicon-m-archive-box')
            ->color('info')
            ->visible(fn ($record) => $record->status === ProductionOrderStatus::RELEASED)
            ->schema([
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->default(fn ($record) => $record->remaining_quantity)
                    ->helperText('Quantity to post to inventory'),
            ])
            ->action(function ($record, array $data) {
                try {
                    app(ProductionOrderService::class)->postOutput($record, $data['quantity'], auth()->id());
                    Notification::make()->title('Output successfully posted')->success()->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    public static function finish(): Action
    {
        return Action::make('finish')
            ->label('Finish')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn ($record) => $record->status === ProductionOrderStatus::RELEASED)
            ->action(function ($record) {
                try {
                    app(ProductionOrderService::class)->finish($record, auth()->id());
                    Notification::make()
                        ->title('Production Order Finished')
                        ->body('Unit cost: $'.number_format($record->fresh()->unit_cost, 2))
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn ($record) => ! in_array($record->status, [ProductionOrderStatus::FINISHED, ProductionOrderStatus::CANCELLED]))
            ->action(function ($record) {
                try {
                    app(ProductionOrderService::class)->cancel($record, auth()->id());
                    Notification::make()->title('Production Order Cancelled')->warning()->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label('Reopen')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn ($record) => $record->status === ProductionOrderStatus::FINISHED)
            ->action(function ($record) {
                try {
                    app(ProductionOrderService::class)->reopen($record);
                    Notification::make()->title('Production Order Reopened')->info()->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    protected static function error(\Exception $e): void
    {
        Notification::make()
            ->title('Error')
            ->body($e->getMessage())
            ->danger()
            ->send();
    }
}
