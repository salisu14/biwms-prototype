<?php

namespace App\Filament\Resources\PettyCashFunds\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PettyCashFundInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fund Information')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextEntry::make('code')->label('Fund Code')->badge()->color('primary'),
                        TextEntry::make('name')->label('Fund Name')->weight('bold'),
                        TextEntry::make('location')->placeholder('—')->icon('heroicon-o-map-pin'),
                        TextEntry::make('custodian.name')->label('Custodian')->placeholder('—')->icon('heroicon-o-user'),
                        IconEntry::make('is_active')->label('Active Status')->boolean(),
                    ])->columns(3),

                Section::make('Financial Overview')
                    ->icon('heroicon-o-chart-pie')
                    ->schema([
                        TextEntry::make('imprest_amount')
                            ->label('Imprest Amount')
                            ->money(fn ($record) => $record->currency ?? 'NGN'),
                        TextEntry::make('current_balance')
                            ->label('Current Balance')
                            ->money(fn ($record) => $record->currency ?? 'NGN')
                            ->color('success'),
                        TextEntry::make('utilization')
                            ->label('Utilization')
                            ->state(function ($record) {
                                if ($record->imprest_amount <= 0) return '0%';
                                $used = $record->imprest_amount - $record->current_balance;
                                return round(($used / $record->imprest_amount) * 100, 1) . '%';
                            })
                            ->color(fn ($state) => (float) str_replace('%', '', $state) > 80 ? 'danger' : 'info'),
//                            ->description('Percentage of imprest spent.'),
                        TextEntry::make('currency')->badge()->color('gray'),
                    ])->columns(2),

                Section::make('Notes & Audit')
                    ->collapsed()
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('updated_at')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ])->columns(2),
            ]);
    }
}
