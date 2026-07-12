<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\RelationManagers;

use App\Filament\Resources\WorkforceRotationTemplates\WorkforceRotationTemplateResource;
use App\Models\WorkforceRotationTemplateDay;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DaysRelationManager extends RelationManager
{
    protected static string $relationship = 'days';

    protected static ?string $relatedResource = WorkforceRotationTemplateResource::class;

    protected static ?string $title = 'Rotation Days';

    protected static string|null|\BackedEnum $icon = 'heroicon-o-calendar-days';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextInput::make('sequence_day')
                            ->label('Day #')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (RelationManager $livewire): int => $livewire->getOwnerRecord()->cycle_length_days
                            )
                            ->helperText(fn (RelationManager $livewire): string => "Max: {$livewire->getOwnerRecord()->cycle_length_days} days"
                            )
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (empty($state)) {
                                    return;
                                }
                                $set('notes', "Day {$state} of rotation cycle");
                            }),

                        Toggle::make('is_rest_day')
                            ->label('Rest Day')
                            ->inline(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?bool $state) {
                                if ($state) {
                                    $set('employee_shift_id', null);
                                    $set('roster_role_id', null);
                                }
                            }),

                        Select::make('employee_shift_id')
                            ->label('Shift')
                            ->relationship('shift', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn (Get $get): bool => (bool) $get('is_rest_day'))
                            ->required(fn (Get $get): bool => ! $get('is_rest_day'))
                            ->placeholder(fn (Get $get): ?string => $get('is_rest_day') ? 'Rest day — no shift' : 'Select shift'
                            ),

                        Select::make('attendance_location_id')
                            ->label('Location')
                            ->relationship('attendanceLocation', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Optional location'),

                        Select::make('work_center_id')
                            ->label('Work Center')
                            ->relationship('workCenter', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Optional work center'),

                        Select::make('roster_role_id')
                            ->label('Roster Role')
                            ->relationship('rosterRole', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn (Get $get): bool => (bool) $get('is_rest_day'))
                            ->placeholder(fn (Get $get): ?string => $get('is_rest_day') ? 'Rest day — no role' : 'Optional role'
                            ),
                    ]),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->maxLength(500)
                    ->placeholder('Optional notes for this day...')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sequence_day')
            ->heading('Rotation Cycle Days')
            ->description('Define each day in the rotation cycle. Days are ordered by sequence.')
            ->emptyStateHeading('No rotation days configured')
            ->emptyStateDescription('Add days to build the rotation template cycle.')
            ->emptyStateIcon('heroicon-o-calendar')
            ->defaultSort('sequence_day', 'asc')
            ->reorderable('sequence_day')
            ->columns([
                TextColumn::make('sequence_day')
                    ->label('Day')
                    ->alignCenter()
                    ->weight('font-bold')
                    ->width('60px')
                    ->sortable(),

                IconColumn::make('is_rest_day')
                    ->label('Rest')
                    ->boolean()
                    ->trueIcon('heroicon-o-moon')
                    ->falseIcon('heroicon-o-briefcase')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),

                TextColumn::make('shift.name')
                    ->label('Shift')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'primary' : 'gray')
                    ->formatStateUsing(fn (?string $state, WorkforceRotationTemplateDay $record): string => $record->is_rest_day ? 'Rest Day' : ($state ?? 'Unassigned')
                    ),

                TextColumn::make('attendanceLocation.name')
                    ->label('Location')
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('rosterRole.name')
                    ->label('Role')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (WorkforceRotationTemplateDay $record): ?string => $record->notes)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                TernaryFilter::make('is_rest_day')
                    ->label('Rest Days')
                    ->placeholder('All days')
                    ->trueLabel('Rest days only')
                    ->falseLabel('Working days only'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Day')
                    ->icon('heroicon-m-plus')
                    ->modalHeading('Add Rotation Day')
                    ->modalDescription('Configure a day in the rotation cycle.')
                    ->mutateFormDataBeforeCreate(function (array $data, RelationManager $livewire): array {
                        $data['workforce_rotation_template_id'] = $livewire->getOwnerRecord()->getKey();

                        return $data;
                    })
                    ->after(function (RelationManager $livewire) {
                        $this->resequenceDays($livewire->getOwnerRecord());
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Edit Rotation Day')
                    ->modalDescription('Update the configuration for this day.'),

                DeleteAction::make()
                    ->after(function (RelationManager $livewire) {
                        $this->resequenceDays($livewire->getOwnerRecord());
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function (RelationManager $livewire) {
                            $this->resequenceDays($livewire->getOwnerRecord());
                        }),
                ]),
            ])
            ->paginated(false);
    }

    /**
     * Ensure sequence_day values remain contiguous after create/delete/reorder.
     */
    private function resequenceDays(Model $template): void
    {
        $template->days()
            ->orderBy('sequence_day')
            ->get()
            ->each(function (WorkforceRotationTemplateDay $day, int $index) {
                $expected = $index + 1;
                if ($day->sequence_day !== $expected) {
                    $day->update(['sequence_day' => $expected]);
                }
            });
    }
}
