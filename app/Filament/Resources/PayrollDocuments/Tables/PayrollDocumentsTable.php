<?php

namespace App\Filament\Resources\PayrollDocuments\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period_start')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (\App\Models\PayrollDocument $record) => $record->status === \App\Enums\PayrollStatus::DRAFT)
                    ->action(function (\App\Models\PayrollDocument $record, \Filament\Notifications\Notification $notification) {
                        try {
                            app(\App\Services\PayrollPostingService::class)->post($record);
                            $notification->success()->title('Payroll Posted!')->send();
                        } catch (\Exception $e) {
                            $notification->danger()->title('Posting Failed')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
