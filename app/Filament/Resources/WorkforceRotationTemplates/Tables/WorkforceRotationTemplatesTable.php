<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\Tables;

use App\Models\WorkforceRotationTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WorkforceRotationTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code', 'asc')
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->recordActions(self::getActions())
            ->toolbarActions(self::getBulkActions())
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateDescription('No rotation templates found.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create Template')
                    ->url(route('filament.admin.resources.workforce-rotation-templates.create'))
                    ->button()
                    ->icon('heroicon-o-plus'),
            ]);
    }

    private static function getColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label('#')
//                ->toggleable(isToggledByDefault: false)
                ->sortable(),

            TextColumn::make('code')
                ->label('Code')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->copyable()
                ->copyMessage('Template code copied!'),

            TextColumn::make('name')
                ->label('Template Name')
                ->searchable()
                ->sortable()
                ->limit(50)
                ->tooltip(fn (WorkforceRotationTemplate $t): string => $t->name),

            TextColumn::make('cycle_length_days')
                ->label('Cycle')
                ->sortable()
                ->alignment('center')
                ->formatStateUsing(fn (int $s): string => "{$s}d")
                ->badge()
                ->color('primary')
                ->tooltip(fn (WorkforceRotationTemplate $t): string => number_format($t->cycle_length_days / 7, 1).' week cycle'
                ),

            IconColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->trueColor('success')
                ->falseIcon('heroicon-o-x-circle')
                ->falseColor('gray'),

            TextColumn::make('configured_days_count')
                ->label('Days Set')
                ->counts('days')
                ->sortable()
                ->alignment('center')
                ->badge()
                ->color(fn (int $state, WorkforceRotationTemplate $t): string => $state >= $t->cycle_length_days ? 'success' : ($state > 0 ? 'warning' : 'danger')
                )
                ->formatStateUsing(fn (int $state, WorkforceRotationTemplate $t): string => "{$state}/{$t->cycle_length_days}"
                ),

            TextColumn::make('effective_from')
                ->label('Valid From')
                ->date('M d, Y')
                ->sortable()
                ->toggleable()
                ->placeholder('—')
                ->color('gray'),

            TextColumn::make('effective_to')
                ->label('Valid To')
                ->date('M d, Y')
                ->sortable()
                ->toggleable()
                ->placeholder('∞')
                ->color('gray'),
        ];
    }

    private static function getFilters(): array
    {
        return [
            SelectFilter::make('is_active')
                ->label('Status')
                ->options([
                    '1' => 'Active',
                    '0' => 'Inactive',
                ]),

            Filter::make('has_full_cycle')
                ->label('Fully Configured')
                ->toggle()
                ->query(function ($query, $state): mixed {
                    if (! $query instanceof Builder) {
                        return $query;
                    }

                    $enabled = is_array($state) ? ($state['isActive'] ?? false) : (bool) $state;
                    if (! $enabled) {
                        return $query;
                    }

                    return $query->whereHas('days', fn ($q) => $q, '=', function ($q) {
                        $q->havingRaw('COUNT(*) >= workforce_rotation_templates.cycle_length_days');
                    });
                }),

            Filter::make('cycle_range')
                ->label('Cycle Length Range')
                ->schema([
                    TextInput::make('min_days')->label('Min Days')->numeric()->default(1),
                    TextInput::make('max_days')->label('Max Days')->numeric()->default(365),
                ])
                ->query(function ($query, array $data): mixed {
                    if (! $query instanceof Builder) {
                        return $query;
                    }

                    $min = (int) ($data['min_days'] ?? 0);
                    $max = (int) ($data['max_days'] ?? 365);

                    return $query->whereBetween('cycle_length_days', [$min, $max]);
                }),
        ];
    }

    private static function getActions(): array
    {
        return [
            ViewAction::make(),

            EditAction::make(),

            Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
//                ->confirmationTitle('Duplicate this template?')
//                ->confirmationQuestion('This will create a copy with all day configurations.')
                ->color('warning')
                ->action(function (WorkforceRotationTemplate $record) {
                    $new = $record->replicate(['is_active']);
                    $new->name = $record->name.' (Copy)';
                    $new->code = $record->code.'-COPY-'.now()->format('YmdHi');
                    $new->push();

                    // Duplicate days too
                    foreach ($record->days as $day) {
                        $newDay = $day->replicate();
                        $newDay->workforce_rotation_template_id = $new->id;
                        $newDay->push();
                    }
                }),

            Action::make('toggle_active')
                ->label(fn (WorkforceRotationTemplate $t): string => $t->is_active ? 'Deactivate' : 'Activate')
                ->icon(fn (WorkforceRotationTemplate $t): string => $t->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle'
                )
                ->requiresConfirmation()
                ->color(fn (WorkforceRotationTemplate $t): string => $t->is_active ? 'warning' : 'success'
                )
                ->action(fn (WorkforceRotationTemplate $t) => $t->update(['is_active' => ! $t->is_active])
                ),
        ];
    }

    private static function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),

                Action::make('bulk_activate')
                    ->label('Activate Selected')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(fn (Collection $records) => $records->each(fn (WorkforceRotationTemplate $t) => $t->update(['is_active' => true])
                    )
                    ),

                Action::make('bulk_deactivate')
                    ->label('Deactivate Selected')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->action(fn (Collection $records) => $records->each(fn (WorkforceRotationTemplate $t) => $t->update(['is_active' => false])
                    )
                    ),
            ]),
        ];
    }
}
