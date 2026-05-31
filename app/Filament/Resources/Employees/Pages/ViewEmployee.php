<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Department;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePromotionHistory;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                        ->maxLength(255)
                        ->default(fn () => (string) $this->record->job_title),
                    Select::make('new_department_id')
                        ->label('New Department')
                        ->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->default(fn () => $this->record->department_id),
                    TextInput::make('new_base_salary')
                        ->required()
                        ->numeric()
                        ->default(fn () => (float) $this->record->getCurrentBaseSalary()),
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
                }),
            EditAction::make(),
        ];
    }
}
