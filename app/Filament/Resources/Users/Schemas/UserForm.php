<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile Information')
                    ->description('Basic account details.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(100), // Match DTO max:100

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->unique(User::class, 'email'),
                        ]),
                    ])
                    ->columns(1),

                Section::make('Security')
                    ->description('Set a password to secure the account.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->required(fn (string $operation) => $operation === 'create')
                                ->confirmed()
                                ->minLength(8)
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn ($state) => filled($state))
                                ->helperText('Minimum 8 characters'),

                            TextInput::make('password_confirmation')
                                ->label('Confirm Password')
                                ->password()
                                ->required(fn (string $operation) => $operation === 'create')
                                ->dehydrated(false),
                        ]),
                    ])
                    ->columns(1),

                Section::make('Access Control')
                    ->description('Assign roles to this user.')
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
