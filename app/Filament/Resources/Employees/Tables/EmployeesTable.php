<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Models\Employee;
use App\Services\HR\EmployeeOnboardingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_number')
                    ->label('No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn ($state, $record): string => "{$record->employee_number} - {$state}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('job_title')
                    ->searchable(),
                TextColumn::make('assignment_type')
                    ->label('Assignment')
                    ->badge()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('business_code')
                    ->label('Business')
                    ->badge()
                    ->color('info'),
                TextColumn::make('factory_code')
                    ->label('Factory')
                    ->badge()
                    ->color('success'),
                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('createLoginAccount')
                        ->label('Create Login Account')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->visible(fn (Employee $record): bool => ! $record->hasUserAccount())
                        ->form([
                            TextInput::make('login_email')
                                ->label('Login Email')
                                ->email()
                                ->required()
                                ->unique(table: 'users', column: 'email')
                                ->validationMessages(['unique' => 'This login email is already in use by another user.']),
                            Select::make('initial_role')
                                ->label('Initial Role')
                                ->required()
                                ->options(fn (): array => Role::query()
                                    ->where('guard_name', 'web')
                                    ->orderBy('name')
                                    ->pluck('name', 'name')
                                    ->all())
                                ->searchable()
                                ->preload(),
                            ToggleButtons::make('password_method')
                                ->label('Password Setup Method')
                                ->options([
                                    'send_password_reset' => 'Send Password Reset Link',
                                    'temporary_password' => 'Set Temporary Password',
                                ])
                                ->inline()
                                ->required()
                                ->default('send_password_reset')
                                ->live(),
                            TextInput::make('temporary_password')
                                ->label('Temporary Password')
                                ->password()
                                ->required(fn (Get $get): bool => $get('password_method') === 'temporary_password')
                                ->minLength(8)
                                ->visible(fn (Get $get): bool => $get('password_method') === 'temporary_password')
                                ->dehydrated(fn (Get $get): bool => $get('password_method') === 'temporary_password'),
                        ])
                        ->action(function (Employee $record, array $data): void {
                            app(EmployeeOnboardingService::class)->createUserAccountForEmployee($record, $data);

                            Notification::make()
                                ->title('Login account created')
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete Selected'),
                ]),
            ]);
    }
}
