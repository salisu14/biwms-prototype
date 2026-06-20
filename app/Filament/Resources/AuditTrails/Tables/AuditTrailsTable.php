<?php

namespace App\Filament\Resources\AuditTrails\Tables;

use App\Models\AuditTrail;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditTrailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('event_type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document_type')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('document_no')
                    ->label('Document No.')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('auditable_type')
                    ->label('Auditable')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->options(fn (): array => self::distinctOptions('event_type')),
                SelectFilter::make('action')
                    ->options(fn (): array => self::distinctOptions('action')),
                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('auditable_type')
                    ->options(fn (): array => self::distinctOptions('auditable_type')),
                Filter::make('occurred_at')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private static function distinctOptions(string $column): array
    {
        return AuditTrail::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }
}
