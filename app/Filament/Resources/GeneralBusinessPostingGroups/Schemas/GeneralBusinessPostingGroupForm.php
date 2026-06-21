<?php

namespace App\Filament\Resources\GeneralBusinessPostingGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralBusinessPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Identification')
                    ->description('Primary classification for customers and vendors (e.g., Regional vs Foreign).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Business Group Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., DOMESTIC')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Local market customers and vendors'),
                    ]),

                Section::make('VAT & Automation')
                    ->description('Configure how taxes are automatically assigned to entities in this group.')
                    ->columns(2)
                    ->schema([
                        Select::make('default_vat_business_posting_group_id')
                            ->label('Default VAT Bus. Posting Group')
                            ->relationship('defaultVatBusinessPostingGroup', 'code')
                            ->searchable()
                            ->preload()
                            ->helperText('The default tax category for entities assigned to this business group.'),

                        Grid::make(1)->schema([
                            Toggle::make('auto_create_vat_bus_posting_group')
                                ->label('Auto-Assign VAT Group')
                                ->helperText('Automatically populate the VAT group when creating a new Customer or Vendor.')
                                ->default(false)
                                ->inline(false),

                            Toggle::make('blocked')
                                ->label('Blocked')
                                ->helperText('Prevent this group from being assigned to new entities or journals.')
                                ->default(false)
                                ->inline(false)
                                ->onColor('danger')
                                ->offColor('success'),
                        ]),
                    ]),
            ]);
    }
}
