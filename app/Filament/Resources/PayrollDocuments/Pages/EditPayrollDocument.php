<?php

namespace App\Filament\Resources\PayrollDocuments\Pages;

use App\Enums\PayrollStatus;
use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Traits\ShowsMissingApprovalTemplateWarning;
use App\Models\Employee;
use App\Models\PayCode;
use App\Models\PayrollLine;
use App\Services\PayrollCalculationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayrollDocument extends EditRecord
{
    use ShowsMissingApprovalTemplateWarning;

    protected static string $resource = PayrollDocumentResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $this->warnIfMissingApprovalTemplate($this->record, 'Payroll Document');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reseedMissingEmployees')
                ->label('Re-seed Missing Employees')
                ->icon('heroicon-o-users')
                ->color('info')
                ->visible(fn () => $this->record->status !== PayrollStatus::POSTED)
                ->form([
                    Select::make('department_id')
                        ->label('Department')
                        ->options(fn (): array => Employee::query()
                            ->whereNotNull('department_id')
                            ->orderBy('department_id')
                            ->distinct()
                            ->pluck('department_id', 'department_id')
                            ->all())
                        ->searchable(),
                    Select::make('factory_code')
                        ->label('Location/Factory')
                        ->options(fn (): array => Employee::query()
                            ->whereNotNull('factory_code')
                            ->orderBy('factory_code')
                            ->distinct()
                            ->pluck('factory_code', 'factory_code')
                            ->all())
                        ->searchable(),
                    Select::make('payroll_posting_group_id')
                        ->label('Payroll Posting Group')
                        ->options(fn (): array => Employee::query()
                            ->whereNotNull('payroll_posting_group_id')
                            ->orderBy('payroll_posting_group_id')
                            ->distinct()
                            ->pluck('payroll_posting_group_id', 'payroll_posting_group_id')
                            ->all())
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $existingEmployeeIds = $this->record->lines()
                        ->distinct()
                        ->pluck('employee_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $employees = Employee::query()
                        ->where('is_active', true)
                        ->when($data['department_id'] ?? null, fn ($query, $value) => $query->where('department_id', $value))
                        ->when($data['factory_code'] ?? null, fn ($query, $value) => $query->where('factory_code', $value))
                        ->when($data['payroll_posting_group_id'] ?? null, fn ($query, $value) => $query->where('payroll_posting_group_id', $value))
                        ->whereNotIn('id', $existingEmployeeIds)
                        ->orderBy('employee_number')
                        ->get();

                    if ($employees->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('No missing employees found for selected filters')
                            ->send();

                        return;
                    }

                    $defaultPayCodeId = PayCode::query()
                        ->where('type', 'EARNING')
                        ->orderBy('code')
                        ->value('id');

                    foreach ($employees as $employee) {
                        PayrollLine::query()->create([
                            'payroll_document_id' => $this->record->id,
                            'employee_id' => $employee->id,
                            'pay_code_id' => $defaultPayCodeId,
                            'line_type' => 'Earning',
                            'amount' => (float) $employee->getCurrentBaseSalary(),
                            'hours' => null,
                            'rate' => null,
                            'employer_amount' => null,
                            'description' => (float) $employee->getCurrentBaseSalary() > 0
                                ? 'Re-seeded base salary line'
                                : 'Re-seeded line (salary not configured)',
                        ]);
                    }

                    app(PayrollCalculationService::class)->updateDocumentTotals($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title("Added {$employees->count()} missing employees")
                        ->send();
                }),
            Action::make('review')
                ->label('Review')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(fn (): string => PayrollDocumentResource::getUrl('review', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
