<?php

namespace App\Filament\Resources\MaintenanceContracts\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MaintenanceContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_no')->label('Contract No.')->searchable()->sortable(),
                TextColumn::make('description')->searchable()->limit(40),
                TextColumn::make('vendor.vendor_name')->label('Vendor')->searchable(),
                TextColumn::make('contract_type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('end_date')->date()->sortable(),
                TextColumn::make('contract_value')->money('USD')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'terminated' => 'Terminated',
                        'renewal_pending' => 'Renewal Pending',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => in_array($record->status?->value ?? $record->status, ['draft', 'renewal_pending'], true))
                    ->action(function ($record): void {
                        $record->approve((int) auth()->id());
                        Notification::make()->title('Contract activated')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
