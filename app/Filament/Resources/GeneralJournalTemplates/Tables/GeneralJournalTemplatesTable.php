<?php

namespace App\Filament\Resources\GeneralJournalTemplates\Tables;

use App\Enums\JournalTemplateType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GeneralJournalTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('template_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (JournalTemplateType $state) => match ($state) {
                        JournalTemplateType::GENERAL => 'primary',
                        JournalTemplateType::RECURRING => 'warning',
                        JournalTemplateType::FIXED_ASSET => 'info',
                        JournalTemplateType::PAYROLL => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('defaultBalancingAccount.name')
                    ->label('Default Bal. Account')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('source_code')
                    ->label('Source Code')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('force_balancing_account')
                    ->label('Force Bal.')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('test_report_before_posting')
                    ->label('Test Report')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('template_type')
                    ->label('Type')
                    ->options(JournalTemplateType::class)
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('name');
    }
}
