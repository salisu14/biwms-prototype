<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referrers\Tables;

use App\Enums\ReferrerType;
use App\Models\Referrer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReferrersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(32),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => $state?->color())
                    ->sortable(),
                TextColumn::make('linked_entity')
                    ->label('Linked Entity')
                    ->state(fn (Referrer $record): string => $record->linkedEntityLabel())
                    ->toggleable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('commission_eligible')
                    ->label('Commission')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ReferrerType::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('commission_eligible')
                    ->label('Commission Eligible'),
                TrashedFilter::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed())
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }
}
