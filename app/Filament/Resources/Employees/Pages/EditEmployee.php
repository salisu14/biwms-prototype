<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createUserAccount')
                ->label('Create User Account')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->visible(fn (): bool => $this->record->user === null)
                ->form([
                    TextInput::make('name')
                        ->required()
                        ->default(fn (): string => trim("{$this->record->first_name} {$this->record->last_name}")),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->default(fn (): string => (string) ($this->record->email ?? '')),
                    TextInput::make('password')
                        ->password()
                        ->required()
                        ->minLength(8),
                    Select::make('roles')
                        ->options(fn (): array => Role::query()->where('guard_name', 'web')->pluck('name', 'name')->all())
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data): void {
                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                        'employee_id' => $this->record->id,
                    ]);

                    $user->syncRoles($data['roles'] ?? []);

                    Notification::make()
                        ->success()
                        ->title('User account created')
                        ->body("Employee {$this->record->employee_number} is now linked to {$user->email}.")
                        ->send();
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
