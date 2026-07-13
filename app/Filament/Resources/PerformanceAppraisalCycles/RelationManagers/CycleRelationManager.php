<?php

namespace App\Filament\Resources\PerformanceAppraisalCycles\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CycleRelationManager extends RelationManager
{
    protected static string $relationship = 'cycle';

    protected static ?string $title = 'Appraisal Cycle Details';

    protected static ?string $recordTitleAttribute = 'name';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Cycle Information')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->weight('bold'),

                        TextEntry::make('name')
                            ->label('Name')
                            ->size(TextSize::Large),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),

                        TextEntry::make('cycle_type')
                            ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                            ->badge(),

                        TextEntry::make('status')
                            ->colors([
                                'gray' => 'draft',
                                'success' => 'open',
                                'info' => fn (string $state) => in_array($state, [
                                    'goal_setting', 'self_assessment', 'manager_review',
                                    'moderation', 'finalization'
                                ]),
                                'warning' => 'reopened',
                                'danger' => 'cancelled',
                                'primary' => 'completed',
                                'secondary' => 'closed',
                            ])
                            ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                    ])
                    ->columns(3),

                Section::make('Timeline')
                    ->schema([
                        TextEntry::make('period_start')
                            ->date()
                            ->label('Period Start'),

                        TextEntry::make('period_end')
                            ->date()
                            ->label('Period End'),

                        TextEntry::make('acknowledgement_deadline')
                            ->date()
                            ->label('Acknowledgement Deadline'),
                    ])
                    ->columns(3),

                Section::make('Configuration')
                    ->schema([
                        Toggle::make('allow_self_assessment'),

                        Toggle::make('allow_peer_review'),

                        Toggle::make('require_moderation'),

                        Toggle::make('lock_completed_reviews'),
                    ])
                    ->columns(4),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->label('Start'),

                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->label('End'),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
