<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Department;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePromotionHistory;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getHeading(): string
    {
        return 'Employee No. '.($this->record->employee_number ?? '—')
            .' • Scope '.($this->record->full_name ?? '—')
            .' • Attribute '.($this->record->assignment_type?->getLabel() ?? '—');
    }

    public function getSubheading(): string
    {
        return 'No. '.($this->record->employee_number ?? '—')
            .' • Scope '.($this->record->full_name ?? '—')
            .' • Attribute '.($this->record->job_title ?: 'Unassigned');
    }

    public function getBreadcrumb(): string
    {
        return ($this->record->employee_number ?? '—')
            .' - '.($this->record->full_name ?? '—');
    }

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
                        ->preload(false),
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
            Action::make('promoteEmployee')
                ->label('Promote Employee')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('warning')
                ->form([
                    DatePicker::make('effective_date')
                        ->required()
                        ->default(now()->toDateString()),
                    TextInput::make('new_job_title')
                        ->required()
                        ->maxLength(255),
                    Select::make('new_department_id')
                        ->label('New Department')
                        ->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(false),
                    TextInput::make('new_base_salary')
                        ->required()
                        ->numeric(),
                    TextInput::make('reason_code')
                        ->default('PROMOTION')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('audit_note')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        $oldJobTitle = $this->record->job_title;
                        $oldDepartmentId = $this->record->department_id;
                        $oldBaseSalary = (float) $this->record->getCurrentBaseSalary();

                        $this->record->job_title = $data['new_job_title'];
                        if (! empty($data['new_department_id'])) {
                            $this->record->department_id = (int) $data['new_department_id'];
                        }
                        $this->record->save();

                        EmployeeCompensation::query()->updateOrCreate(
                            [
                                'employee_id' => $this->record->id,
                                'effective_date' => $data['effective_date'],
                            ],
                            [
                                'base_salary' => $data['new_base_salary'],
                                'reason_code' => $data['reason_code'],
                                'audit_note' => $data['audit_note'],
                                'job_title' => $data['new_job_title'],
                            ]
                        );

                        EmployeePromotionHistory::query()->create([
                            'employee_id' => $this->record->id,
                            'effective_date' => $data['effective_date'],
                            'reason_code' => $data['reason_code'],
                            'old_job_title' => $oldJobTitle,
                            'new_job_title' => $data['new_job_title'],
                            'old_department_id' => $oldDepartmentId,
                            'new_department_id' => $this->record->department_id,
                            'old_base_salary' => $oldBaseSalary,
                            'new_base_salary' => (float) $data['new_base_salary'],
                            'audit_note' => $data['audit_note'],
                            'promoted_by' => auth()->id(),
                        ]);
                    });

                    Notification::make()
                        ->success()
                        ->title('Promotion recorded')
                        ->body("{$this->record->employee_number} updated with effective-dated compensation.")
                        ->send();

                    $this->refreshFormData([
                        'job_title',
                        'department_id',
                    ]);
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
