<?php

namespace App\Filament\Resources\ProductionOrders\Actions;

use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOrder;
use App\Services\Manufacturing\ExpectedManufacturingCostService;
use App\Services\Manufacturing\MultiLevelProductionPlanningService;
use App\Services\Manufacturing\ProductionHierarchyProgressService;
use App\Services\Manufacturing\ProductionOrderCostSettlementService;
use App\Services\Manufacturing\ProductionOrderService;
use App\Support\DecimalFormatter;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
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

    public static function planMultiLevelProduction(): Action
    {
        return Action::make('planMultiLevelProduction')
            ->label('Plan Multi-Level')
            ->icon('heroicon-m-squares-plus')
            ->color('info')
            ->modalHeading('Plan Multi-Level Production')
            ->modalDescription(function (ProductionOrder $record): string {
                try {
                    $preview = app(MultiLevelProductionPlanningService::class)->preview($record);

                    return sprintf(
                        'Preview: %d hierarchy node(s), %d manufactured component(s), max depth %d. Planning creates child production orders, supply links, and reservations only.',
                        $preview['node_count'],
                        $preview['manufactured_count'],
                        $preview['max_depth'],
                    );
                } catch (\Exception $e) {
                    return $e->getMessage();
                }
            })
            ->requiresConfirmation()
            ->visible(fn ($record): bool => $record instanceof ProductionOrder && $record->status->isEditable())
            ->action(function (ProductionOrder $record): void {
                try {
                    $result = app(MultiLevelProductionPlanningService::class)->plan($record, auth()->id());

                    Notification::make()
                        ->title('Multi-level production planned')
                        ->body(sprintf(
                            '%d hierarchy node(s), %d generated child order(s), %d manufactured component(s).',
                            $result['node_count'],
                            $result['child_order_count'],
                            $result['manufactured_component_count'],
                        ))
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    public static function refreshHierarchyStatus(): Action
    {
        return Action::make('refreshHierarchyStatus')
            ->label('Refresh Hierarchy')
            ->icon('heroicon-m-arrow-path-rounded-square')
            ->color('gray')
            ->visible(fn ($record): bool => $record instanceof ProductionOrder && (
                $record->productionHierarchies()->exists()
                || $record->supplyLinksAsChild()->exists()
                || $record->supplyLinksAsParent()->exists()
            ))
            ->action(function (ProductionOrder $record): void {
                try {
                    app(ProductionHierarchyProgressService::class)->syncForOrder($record);

                    Notification::make()
                        ->title('Hierarchy status refreshed')
                        ->success()
                        ->send();
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
            ->visible(fn ($record) => (auth()->user()?->can('postOutput', $record) ?? false) && $record->status === ProductionOrderStatus::RELEASED && (float) $record->remaining_quantity > 0)
            ->schema([
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->step('0.00000001')
                    ->minValue('0.00000001')
                    ->default(fn (ProductionOrder $record): float => self::postOutputDefaultQuantity($record))
                    ->helperText(fn (ProductionOrder $record): string => self::postOutputHelperText($record))
                    ->rules([
                        fn (ProductionOrder $record) => function (string $attribute, $value, \Closure $fail) use ($record): void {
                            $quantityInOrderUom = (float) $value;
                            $quantityBase = self::convertOrderUomToBase($record, $quantityInOrderUom);

                            if ($quantityBase > (float) $record->remaining_quantity) {
                                $fail('Cannot post more than the remaining production output.');
                            }
                        },
                    ]),
            ])
            ->action(function ($record, array $data) {
                try {
                    $quantityInOrderUom = (float) $data['quantity'];
                    $quantityBase = self::convertOrderUomToBase($record, $quantityInOrderUom);

                    app(ProductionOrderService::class)->postOutput($record, $quantityBase, auth()->id());
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
            ->visible(fn ($record) => (auth()->user()?->can('finish', $record) ?? false) && $record->status === ProductionOrderStatus::RELEASED)
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

    public static function calculateExpectedCost(): Action
    {
        return Action::make('calculateExpectedCost')
            ->label('Calculate Expected Cost')
            ->icon('heroicon-m-calculator')
            ->color('gray')
            ->visible(fn ($record): bool => auth()->user()?->can('calculateExpectedCost', $record) ?? false)
            ->action(function (ProductionOrder $record): void {
                try {
                    $result = app(ExpectedManufacturingCostService::class)->calculate($record, userId: auth()->id());

                    Notification::make()
                        ->title('Expected manufacturing cost calculated')
                        ->body('Snapshot #'.$result['snapshot']->id.' created or reused.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    self::error($e);
                }
            });
    }

    public static function settleProductionCost(): Action
    {
        return SensitiveActionPasswordConfirmation::protect(
            Action::make('settleProductionCost')
                ->label('Settle Cost')
                ->icon('heroicon-m-scale')
                ->color('warning')
                ->visible(fn ($record): bool => auth()->user()?->can('settleProductionCost', $record) ?? false)
                ->action(function (ProductionOrder $record): void {
                    try {
                        $result = app(ProductionOrderCostSettlementService::class)->settle($record, auth()->id());

                        Notification::make()
                            ->title($result['settled'] ? 'Production cost settled' : 'Production cost not ready')
                            ->body('Status: '.($result['status'] ?? 'unknown'))
                            ->color($result['settled'] ? 'success' : 'warning')
                            ->send();
                    } catch (\Exception $e) {
                        self::error($e);
                    }
                }),
        );
    }

    public static function viewCostReconciliation(): Action
    {
        return Action::make('viewCostReconciliation')
            ->label('Cost Reconcile')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('gray')
            ->visible(fn ($record): bool => auth()->user()?->can('reconcileProductionCost', $record) ?? false)
            ->url(fn (ProductionOrder $record): string => url('/admin').'?manufacturing-cost-reconcile='.$record->document_number)
            ->openUrlInNewTab();
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

    public static function postOutputDefaultQuantity(ProductionOrder $record): float
    {
        return $record->remainingQuantityInOrderUom();
    }

    public static function postOutputHelperText(ProductionOrder $record): string
    {
        $remainingQuantityBase = (float) $record->remaining_quantity;

        return sprintf(
            'Quantity to post in %s. Base equivalent: %s %s.',
            $record->orderUomCode(),
            self::formatQuantity($remainingQuantityBase),
            $record->baseUomCode(),
        );
    }

    public static function convertOrderUomToBase(ProductionOrder $record, float $quantityInOrderUom): float
    {
        return $record->convertOrderUomQuantityToBase($quantityInOrderUom);
    }

    private static function formatQuantity(float $quantity): string
    {
        return DecimalFormatter::quantityForInput($quantity);
    }
}
