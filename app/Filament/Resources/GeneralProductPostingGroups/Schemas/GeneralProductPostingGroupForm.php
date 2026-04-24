<?php

namespace App\Filament\Resources\GeneralProductPostingGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralProductPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Identification')
                    ->description('Primary classification and naming for the product posting group.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Group Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., RETAIL')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., General retail merchandise'),
                    ]),

                Section::make('VAT & Automation')
                    ->description('Configure default tax behavior and automation triggers.')
                    ->columns(2)
                    ->schema([
                        Select::make('default_vat_product_posting_group_id')
                            ->label('Default VAT Prod. Posting Group')
                            ->relationship('defaultVatProductPostingGroup', 'code')
                            ->searchable()
                            ->preload()
                            ->helperText('The VAT group that will be suggested when this product group is selected on an item.'),

                        Grid::make(1)->schema([
                            Toggle::make('auto_create_vat_prod_posting_group')
                                ->label('Auto-Assign VAT Group')
                                ->helperText('Automatically assign the default VAT group during item creation.')
                                ->default(false)
                                ->inline(false),

                            Toggle::make('blocked')
                                ->label('Blocked from usage')
                                ->helperText('Prevents this group from being assigned to new items or journals.')
                                ->default(false)
                                ->inline(false)
                                ->onColor('danger')
                                ->offColor('success'),
                        ]),
                    ]),
            ]);
    }
}
