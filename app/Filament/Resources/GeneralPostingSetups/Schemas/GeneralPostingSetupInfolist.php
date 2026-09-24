<?php

namespace App\Filament\Resources\GeneralPostingSetups\Schemas;

use App\Models\ChartOfAccount;
use App\Models\GeneralPostingSetup;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralPostingSetupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Posting Setup Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('generalBusinessPostingGroup.code')
                            ->label('Business Group')
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('generalProductPostingGroup.code')
                            ->label('Product Group')
                            ->weight('bold')
                            ->color('primary'),
                        IconEntry::make('blocked')
                            ->boolean()
                            ->label('Status (Blocked)'),
                    ]),

                Section::make('Account Mappings')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('salesAccount.account_number')
                            ->label('Sales Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->salesAccount)),
                        TextEntry::make('salesCreditMemoAccount.account_number')
                            ->label('Sales Credit Memo Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->salesCreditMemoAccount)),
                        TextEntry::make('salesPrepaymentAccount.account_number')
                            ->label('Sales Prepayment Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->salesPrepaymentAccount)),
                        TextEntry::make('cogsAccount.account_number')
                            ->label('COGS Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->cogsAccount)),
                        TextEntry::make('cogsCreditMemoAccount.account_number')
                            ->label('COGS Credit Memo Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->cogsCreditMemoAccount)),
                        TextEntry::make('cogsPrepaymentAccount.account_number')
                            ->label('COGS Prepayment Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->cogsPrepaymentAccount)),
                        TextEntry::make('purchaseAccount.account_number')
                            ->label('Purchase Account / Clearing (GRNI)')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->purchaseAccount)),
                        TextEntry::make('purchaseCreditMemoAccount.account_number')
                            ->label('Purchase Credit Memo Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->purchaseCreditMemoAccount)),
                        TextEntry::make('inventoryAccount.account_number')
                            ->label('Inventory Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->inventoryAccount)),
                        TextEntry::make('inventoryAdjAccount.account_number')
                            ->label('Inventory Adjustment Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->inventoryAdjAccount)),
                        TextEntry::make('purchaseVarianceAccount.account_number')
                            ->label('Purchase Variance Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->purchaseVarianceAccount)),
                        TextEntry::make('materialVarianceAccount.account_number')
                            ->label('Material Variance Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->materialVarianceAccount)),
                        TextEntry::make('capacityVarianceAccount.account_number')
                            ->label('Capacity Variance Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->capacityVarianceAccount)),
                        TextEntry::make('capacityOverheadVarianceAccount.account_number')
                            ->label('Capacity Overhead Variance Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->capacityOverheadVarianceAccount)),
                        TextEntry::make('manufacturingOverheadVarianceAccount.account_number')
                            ->label('Manufacturing Overhead Variance Account')
                            ->placeholder('—')
                            ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->manufacturingOverheadVarianceAccount)),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->compact()
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }

    private static function formatAccount(?ChartOfAccount $account): string
    {
        if (! $account) {
            return '—';
        }

        $number = trim((string) $account->account_number);
        $name = trim((string) $account->name);

        if ($number === '') {
            return $name !== '' ? $name : '—';
        }

        return $name !== '' ? "{$number} — {$name}" : $number;
    }
}
