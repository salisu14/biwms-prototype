<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Exports\UserExporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // TAB-LIKE FILTER: Switch between user types
                SelectFilter::make('user_type')
                    ->label('Filter By')
                    ->options([
                        'academic' => 'Academic Staff',
                        'non_academic' => 'Non-Academic Staff',
                        'student' => 'Student',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $type = $data['value'] ?? null;

                        if ($type === 'academic') {
                            return $query->whereHas('roles', fn($q) => $q->where('name', 'lecturer'));
                        }

                        if ($type === 'student') {
                            return $query->whereHas('roles', fn($q) => $q->where('name', 'student'));
                        }

                        if ($type === 'non_academic') {
                            return $query->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['student', 'lecturer']));
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make('export')
                    ->exporter(UserExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()
                    ->exporter(UserExporter::class),
            ]);
    }
}
