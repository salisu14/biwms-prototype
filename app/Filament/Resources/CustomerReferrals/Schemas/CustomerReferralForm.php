<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerReferrals\Schemas;

use App\Enums\CustomerReferralStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerReferralForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Referral')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 3,
                ])
                ->schema([
                    Select::make('business_id')
                        ->relationship('business', 'name')
                        ->searchable()
                        ->optionsLimit(50),
                    Select::make('customer_id')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->optionsLimit(50)
                        ->required(),
                    Select::make('referrer_id')
                        ->relationship('referrer', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->optionsLimit(50)
                        ->required(),
                    Select::make('status')
                        ->options(CustomerReferralStatus::class)
                        ->default(CustomerReferralStatus::ACTIVE)
                        ->disabled()
                        ->dehydrated(false),
                    Toggle::make('is_primary')
                        ->default(true),
                    DatePicker::make('referred_at'),
                    DatePicker::make('effective_from')
                        ->required()
                        ->default(today()),
                    DatePicker::make('effective_to'),
                    TextInput::make('referral_source')->maxLength(255),
                    TextInput::make('reference')->maxLength(255),
                    Textarea::make('notes')->rows(4)->columnSpanFull(),
                ]),
        ]);
    }
}
