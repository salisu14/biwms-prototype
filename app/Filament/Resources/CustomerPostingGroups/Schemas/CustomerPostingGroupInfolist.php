<?php

namespace App\Filament\Resources\CustomerPostingGroups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('description'),
                TextEntry::make('receivablesAccount.name')
                    ->label('Receivables account'),
                TextEntry::make('payment_disc_debit_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('payment_disc_credit_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('invoice_rounding_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('debit_rounding_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('credit_rounding_account_id')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('blocked')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
