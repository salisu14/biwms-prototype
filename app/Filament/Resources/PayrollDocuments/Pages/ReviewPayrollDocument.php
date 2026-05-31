<?php

namespace App\Filament\Resources\PayrollDocuments\Pages;

use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Models\Employee;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Services\PayrollCalculationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
