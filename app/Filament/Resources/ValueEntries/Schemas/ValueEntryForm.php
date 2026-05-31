<?php

namespace App\Filament\Resources\ValueEntries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ValueEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Value Entry Details')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('entry_no')->required()->numeric()->disabled(),
                                    TextInput::make('item_code')->required(),
                                    TextInput::make('location_code')->required(),
                                    TextInput::make('item_ledger_entry_type')->required(),
                                    DatePicker::make('posting_date')->required(),
                                    DatePicker::make('valuation_date'),
                                ]),
                            ]),
                        Tabs\Tab::make('Costs')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('cost_amount_actual')->numeric()->prefix('$'),
                                    TextInput::make('cost_amount_expected')->numeric()->prefix('$'),
                                    TextInput::make('unit_cost')->numeric()->prefix('$'),
                                    TextInput::make('direct_cost_amount')->numeric()->prefix('$'),
                                    TextInput::make('variance_amount')->numeric()->prefix('$'),
                                ]),
                            ]),
                        Tabs\Tab::make('References')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('production_order_no'),
                                    TextInput::make('purchase_order_no'),
                                    TextInput::make('sales_order_no'),
                                    TextInput::make('vendor_no'),
                                    TextInput::make('customer_no'),
                                ]),
                            ]),
                        Tabs\Tab::make('Accounting')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('gl_posted'),
                                    DatePicker::make('gl_posting_date'),
                                    TextInput::make('gl_account_no'),
                                    TextInput::make('balancing_account_no'),
                                    Toggle::make('cost_adjusted'),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
