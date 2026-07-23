<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referrers\Schemas;

use App\Enums\ReferrerType;
use App\Filament\Traits\HasSystemGeneratedField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReferrerForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->schema([
                        static::makeSystemGeneratedTextInput(
                            'code',
                            'Referrer Code',
                            'Generated automatically from the Referrer number series when the record is created.',
                            'Auto-generated from Number Series (REFERRER)'
                        )
                            ->unique(ignoreRecord: true),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->options(ReferrerType::class)
                            ->default(ReferrerType::INDIVIDUAL)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('contact_id', null);
                                $set('customer_id', null);
                                $set('employee_id', null);
                                $set('vendor_id', null);
                            }),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('commission_eligible')
                            ->label('Commission Eligible')
                            ->default(true),
                    ]),

                Section::make('Linked Record')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Select::make('contact_id')
                            ->label('Contact')
                            ->relationship('contact', 'name')
                            ->searchable()
                            ->optionsLimit(50)
                            ->required(fn (Get $get): bool => $get('type') === ReferrerType::CONTACT->value)
                            ->visible(fn (Get $get): bool => $get('type') === ReferrerType::CONTACT->value),

                        Select::make('customer_id')
                            ->label('Existing Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->optionsLimit(50)
                            ->required(fn (Get $get): bool => $get('type') === ReferrerType::EXISTING_CUSTOMER->value)
                            ->visible(fn (Get $get): bool => $get('type') === ReferrerType::EXISTING_CUSTOMER->value),

                        Select::make('employee_id')
                            ->label('Employee')
                            ->relationship('employee', 'full_name')
                            ->searchable()
                            ->optionsLimit(50)
                            ->required(fn (Get $get): bool => $get('type') === ReferrerType::EMPLOYEE->value)
                            ->visible(fn (Get $get): bool => $get('type') === ReferrerType::EMPLOYEE->value),

                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'vendor_name')
                            ->searchable()
                            ->optionsLimit(50)
                            ->required(fn (Get $get): bool => $get('type') === ReferrerType::VENDOR->value)
                            ->visible(fn (Get $get): bool => $get('type') === ReferrerType::VENDOR->value),
                    ]),

                Section::make('Contact Details')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('city')
                            ->maxLength(255),
                        TextInput::make('state')
                            ->maxLength(255),
                        TextInput::make('country')
                            ->maxLength(255),
                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
