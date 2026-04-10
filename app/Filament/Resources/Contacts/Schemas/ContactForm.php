<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('company_name')
                                    ->maxLength(255),
                                Select::make('type')
                                    ->options(ContactType::class)
                                    ->required(),
                                Select::make('role')
                                    ->options(ContactRole::class)
                                    ->required(),
                            ])->columns(2),

                        Section::make('Contact Details')
                            ->schema([
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('mobile')
                                    ->tel()
                                    ->maxLength(255),
                            ])->columns(3),
                    ])->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Address')
                            ->schema([
                                TextInput::make('address')
                                    ->maxLength(255),
                                TextInput::make('city')
                                    ->maxLength(255),
                                TextInput::make('state')
                                    ->maxLength(255),
                                TextInput::make('postal_code')
                                    ->maxLength(255),
                                TextInput::make('country')
                                    ->maxLength(255),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }
}
