<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollDocuments\Pages;

use App\Enums\PayrollStatus;
use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Resources\SocialSecurityTiers\SocialSecurityTierResource;
use App\Models\Employee;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Services\PayrollCalculationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ReviewPayrollDocument extends Page
{
    protected static string $resource = PayrollDocumentResource::class;

    protected string $view = 'filament.resources.payroll-documents.pages.review-payroll-document';

    protected static ?string $title = 'Review Payroll Document';

    public PayrollDocument $record;

    public function mount(PayrollDocument|int|string $record): void
    {
        if ($record instanceof PayrollDocument) {
            $this->record = $record->load(['period', 'lines.employee', 'lines.payCode']);

            return;
        }

        $this->record = PayrollDocument::query()
            ->with(['period', 'lines.employee', 'lines.payCode'])
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        return 'Payroll Review '.$this->record->document_number;
    }

    public function getSocialSecurityTiersUrl(): string
    {
        return SocialSecurityTierResource::getUrl();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Payroll Documents')
                ->color('gray')
                ->url(PayrollDocumentResource::getUrl('index')),
            Action::make('addAdjustment')
                ->label('Add Bonus/Deduction')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->form([
                    Select::make('employee_id')
                        ->label('Employee')
                        ->options(fn (): array => $this->record->lines
                            ->pluck('employee')
                            ->filter()
                            ->unique('id')
                            ->sortBy('employee_number')
                            ->mapWithKeys(fn (Employee $employee) => [
                                $employee->id => "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}",
                            ])
                            ->all())
                        ->required()
                        ->searchable(),
                    Select::make('line_type')
                        ->options([
                            'Earning' => 'Bonus (Earning)',
                            'Deduction' => 'Deduction',
                        ])
                        ->default('Earning')
                        ->required(),
                    Select::make('pay_code_id')
                        ->label('Pay Code')
                        ->options(fn (): array => PayCode::query()
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (PayCode $payCode) => [
                                $payCode->id => "{$payCode->code} - {$payCode->name}",
                            ])
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('amount')
                        ->numeric()
                        ->required(),
                    TextInput::make('description')
                        ->required()
                        ->maxLength(255)
                        ->default('Manual payroll adjustment'),
                ])
                ->action(function (array $data): void {
                    PayrollLine::query()->create([
                        'payroll_document_id' => $this->record->id,
                        'employee_id' => (int) $data['employee_id'],
                        'pay_code_id' => (int) $data['pay_code_id'],
                        'line_type' => $data['line_type'],
                        'amount' => (float) $data['amount'],
                        'description' => $data['description'],
                    ]);

                    app(PayrollCalculationService::class)->updateDocumentTotals($this->record);

                    $this->record->refresh()->load(['period', 'lines.employee', 'lines.payCode']);
                }),
            Action::make('reseedMissingEmployees')
                ->label('Re-seed Missing Employees')
                ->icon('heroicon-o-users')
                ->color('info')
                ->visible(fn (): bool => $this->record->status !== PayrollStatus::POSTED)
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
                    $this->record->refresh()->load(['period', 'lines.employee', 'lines.payCode']);

                    Notification::make()
                        ->success()
                        ->title("Added {$employees->count()} missing employees")
                        ->send();
                }),
            Action::make('edit')
                ->label('Edit Document')
                ->icon('heroicon-o-pencil-square')
                ->url(PayrollDocumentResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function getEmployeeSummariesProperty()
    {
        return $this->record->lines
            ->groupBy('employee_id')
            ->map(function ($lines) {
                $employee = $lines->first()?->employee;
                $earnings = (float) $lines->where('line_type', 'Earning')->sum('amount');
                $deductions = (float) $lines->where('line_type', 'Deduction')->sum('amount');
                $benefits = (float) $lines->where('line_type', 'Benefit')->sum('amount');

                return [
                    'employee_id' => $employee?->id,
                    'employee_number' => $employee?->employee_number,
                    'employee_name' => trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')),
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                    'benefits' => $benefits,
                    'net' => $earnings - $deductions,
                    'line_count' => $lines->count(),
                ];
            })
            ->values();
    }

    public function getEmployeesMissingPrimaryBankProperty()
    {
        return $this->record->lines
            ->pluck('employee')
            ->filter()
            ->unique('id')
            ->values()
            ->filter(fn ($employee) => ! $employee->bankAccounts()->where('is_primary', true)->exists())
            ->values();
    }
}
