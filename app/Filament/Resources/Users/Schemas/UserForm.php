<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Employee;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create'),
                    ])->columns(2),

                Section::make('Identity & Roles')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Link to Employee')
                            ->options(function (?User $record): array {
                                return Employee::query()
                                    ->where('is_active', true)
                                    ->where(function ($query) use ($record): void {
                                        $query->whereDoesntHave('user');

                                        if ($record?->employee_id) {
                                            $query->orWhere('id', $record->employee_id);
                                        }
                                    })
                                    ->orderBy('employee_number')
                                    ->get()
                                    ->mapWithKeys(fn (Employee $employee): array => [
                                        $employee->id => "{$employee->first_name} {$employee->last_name} ({$employee->employee_number})",
                                    ])
                                    ->all();
                            })
                            ->required()
                            ->helperText('All application users must be linked to one active employee.')
                            ->searchable()
                            ->preload(),

                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                        Select::make('salesperson_code')
                            ->label('Default Salesperson / Purchaser')
                            ->helperText('Used to auto-populate the Salesperson field on orders and invoices.')
                            ->relationship('defaultSalesperson', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
            ]);
    }
}
