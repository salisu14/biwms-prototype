<?php

namespace App\Filament\Resources\ProductionOrders\RelationManagers;

use App\Services\Manufacturing\ProductionOrderService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoutingRelationManager extends RelationManager
{
    protected static string $relationship = 'routingLines';

    protected static ?string $title = 'Routing Operations';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('operation_no')
                    ->required()
                    ->maxLength(255),
                TextInput::make('description')
                    ->required()
                    ->maxLength(255),
                Select::make('setup_time_unit')
                    ->label('Setup UOM')
                    ->relationship('setupTimeUnit', 'uom_code')
                    ->searchable()
                    ->preload(),
                Select::make('run_time_unit')
                    ->label('Run UOM')
                    ->relationship('runTimeUnit', 'uom_code')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('operation_no')
                    ->label('Op No')
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('workCenter.name')
                    ->label('Work Center'),
                TextColumn::make('setup_time')
                    ->label('Setup (Plan)')
                    ->suffix(fn ($record) => " {$record->setup_time_unit}")
                    ->numeric(2),
                TextColumn::make('run_time')
                    ->label('Run (Plan)')
                    ->suffix(fn ($record) => " {$record->run_time_unit}")
                    ->numeric(2),
                TextColumn::make('actual_setup_time')
                    ->label('Setup (Actual)')
                    ->numeric(2)
                    ->color('success'),
                TextColumn::make('actual_run_time')
                    ->label('Run (Actual)')
                    ->numeric(2)
                    ->color('success'),
                TextColumn::make('total_cost')
                    ->money('USD')
                    ->label('Actual Cost'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PLANNED' => 'gray',
                        'IN_PROGRESS' => 'warning',
                        'COMPLETED' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Action::make('post_time')
                    ->label('Post Time')
                    ->icon('heroicon-o-clock')
                    ->color('success')
                    ->form([
                        TextInput::make('setup_time')
                            ->label('Setup Time')
                            ->numeric()
                            ->default(fn ($record) => $record->setup_time - $record->actual_setup_time)
                            ->required(),
                        TextInput::make('run_time')
                            ->label('Run Time')
                            ->numeric()
                            ->default(fn ($record) => $record->run_time - $record->actual_run_time)
                            ->required(),
                        TextInput::make('cost')
                            ->label('Direct Cost')
                            ->numeric()
                            ->required(false)
                            ->helperText('Leave blank to use Work/Machine center standard rates.')
                            ->default(0),
                    ])
                    ->action(function ($record, array $data, $livewire) {
                        app(ProductionOrderService::class)->postCapacity(
                            $livewire->getOwnerRecord(),
                            $record->id,
                            $data['setup_time'],
                            $data['run_time'],
                            $data['cost'],
                            auth()->id()
                        );

                        $record->actual_setup_time += $data['setup_time'];
                        $record->actual_run_time += $data['run_time'];
                        if ($record->actual_run_time >= $record->run_time) {
                            $record->status = 'COMPLETED';
                        } else {
                            $record->status = 'IN_PROGRESS';
                        }
                        $record->save();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
