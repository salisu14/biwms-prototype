<?php
// app/Filament/Resources/Categories/Tables/CategoriesTable.php

namespace App\Filament\Resources\Categories\Tables;

use App\Enums\CategoryType;
use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('category_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('category_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('category_type')
                    ->label('Type')
//                    ->icon(fn ($state): ?string => $state?->icon())
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
//                    ->colors(fn (): array => collect(CategoryType::cases())->mapWithKeys(fn ($case) => [$case->color() => $case->value])->toArray())
//                    ->icon(fn ($state): ?string => CategoryType::tryFrom($state)?->icon())
//                    ->formatStateUsing(fn ($state): string => CategoryType::tryFrom($state)?->label() ?? $state)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parent.category_name')
                    ->label('Parent')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('level')
                    ->label('Lvl')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hierarchy_path')
                    ->label('Path')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(30),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // FIXED: Use options() instead of relationship()
                SelectFilter::make('category_type')
                    ->label('Type')
                    ->options(
                        collect(CategoryType::cases())
                            ->mapWithKeys(fn ($case) => [
                                $case->value => $case->label()
                            ])
                            ->toArray()
                    ),

                SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->options(fn () =>
                    Category::query()
                        ->whereNotNull('category_name')
                        ->where('category_name', '!=', '')
                        ->pluck('category_name', 'id')
                        ->toArray()
                    )
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All Categories')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
