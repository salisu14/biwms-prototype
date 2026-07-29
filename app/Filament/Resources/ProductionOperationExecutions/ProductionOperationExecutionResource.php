<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionOperationExecutions;

use App\Enums\ProductionOperationExecutionStatus;
use App\Filament\Resources\ProductionOperationExecutions\Pages\ListProductionOperationExecutions;
use App\Filament\Resources\ProductionOperationExecutions\Pages\ViewProductionOperationExecution;
use App\Models\Manufacturing\ProductionOperationExecution;
use App\Services\Manufacturing\ProductionOperationExecutionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProductionOperationExecutionResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'factory';
    }

    public static function permissionResource(): string
    {
        return 'production_operation_execution';
    }

    protected static ?string $model = ProductionOperationExecution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlay;

    protected static string|UnitEnum|null $navigationGroup = 'Shop Floor';

    protected static ?string $navigationLabel = 'Operation Executions';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Execution')
                ->columns([
                    'default' => 1,
                    'xl' => 3,
                ])
                ->schema([
                    TextEntry::make('productionOrder.document_number')->label('Production Order'),
                    TextEntry::make('routingLine.operation_no')->label('Operation'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('workCenter.name')->label('Work Center'),
                    TextEntry::make('machineCenter.name')->label('Machine'),
                    TextEntry::make('operatorEmployee.full_name')->label('Operator'),
                    TextEntry::make('good_quantity')->numeric(),
                    TextEntry::make('scrap_quantity')->numeric(),
                    TextEntry::make('rework_quantity')->numeric(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productionOrder.document_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('routingLine.operation_no')
                    ->label('Op.')
                    ->sortable()
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->toggleable(),
                TextColumn::make('machineCenter.name')
                    ->label('Machine')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operatorEmployee.full_name')
                    ->label('Operator')
                    ->toggleable(),
                TextColumn::make('good_quantity')
                    ->label('Good')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('scrap_quantity')
                    ->label('Scrap')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProductionOperationExecutionStatus::class),
                SelectFilter::make('work_center_id')->relationship('workCenter', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('startSetup')
                        ->label('Start Setup')
                        ->icon(Heroicon::OutlinedPlay)
                        ->visible(fn (ProductionOperationExecution $record): bool => in_array($record->status, [ProductionOperationExecutionStatus::Ready, ProductionOperationExecutionStatus::NotStarted], true))
                        ->action(fn (ProductionOperationExecution $record): ProductionOperationExecution => app(ProductionOperationExecutionService::class)->startSetup($record, auth()->id())),
                    Action::make('completeSetup')
                        ->label('Complete Setup')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(fn (ProductionOperationExecution $record): bool => $record->status === ProductionOperationExecutionStatus::SetupStarted)
                        ->action(fn (ProductionOperationExecution $record): ProductionOperationExecution => app(ProductionOperationExecutionService::class)->completeSetup($record, auth()->id())),
                    Action::make('startRun')
                        ->label('Start Run')
                        ->icon(Heroicon::OutlinedPlay)
                        ->visible(fn (ProductionOperationExecution $record): bool => in_array($record->status, [ProductionOperationExecutionStatus::Ready, ProductionOperationExecutionStatus::SetupCompleted, ProductionOperationExecutionStatus::Paused], true))
                        ->action(fn (ProductionOperationExecution $record): ProductionOperationExecution => app(ProductionOperationExecutionService::class)->startRun($record, auth()->id())),
                    Action::make('submit')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->visible(fn (ProductionOperationExecution $record): bool => $record->status === ProductionOperationExecutionStatus::Completed)
                        ->action(fn (ProductionOperationExecution $record): ProductionOperationExecution => app(ProductionOperationExecutionService::class)->submit($record, auth()->id(), createJournal: true)),
                    Action::make('post')
                        ->icon(Heroicon::OutlinedBolt)
                        ->visible(fn (ProductionOperationExecution $record): bool => $record->status === ProductionOperationExecutionStatus::Submitted)
                        ->action(fn (ProductionOperationExecution $record): ProductionOperationExecution => app(ProductionOperationExecutionService::class)->postJournal($record, auth()->id())),
                    Action::make('reverse')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('reason')->required(),
                        ])
                        ->visible(fn (ProductionOperationExecution $record): bool => in_array($record->status, [ProductionOperationExecutionStatus::Submitted, ProductionOperationExecutionStatus::Posted], true))
                        ->action(fn (ProductionOperationExecution $record, array $data): ProductionOperationExecution => app(ProductionOperationExecutionService::class)->reverse($record, (string) $data['reason'], auth()->id())),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionOperationExecutions::route('/'),
            'view' => ViewProductionOperationExecution::route('/{record}'),
        ];
    }
}
