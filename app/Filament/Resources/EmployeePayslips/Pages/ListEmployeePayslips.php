<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeePayslips\Pages;

use App\Enums\PayrollStatus;
use App\Filament\Resources\EmployeePayslips\EmployeePayslipResource;
use App\Models\PayrollDocument;
use App\Services\Hr\EmployeePayslipService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePayslips extends ListRecords
{
    protected static string $resource = EmployeePayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateFromPayroll')
                ->label('Generate from Payroll')
                ->icon('heroicon-o-document-plus')
                ->visible(fn (): bool => auth()->user()?->can('hr.employee_payslip.generate') === true)
                ->schema([
                    Select::make('payroll_document_id')
                        ->label('Payroll Document')
                        ->options(fn (): array => PayrollDocument::query()
                            ->whereIn('status', [PayrollStatus::APPROVED->value, PayrollStatus::POSTED->value])
                            ->latest('id')
                            ->limit(50)
                            ->pluck('document_number', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $document = PayrollDocument::query()->findOrFail($data['payroll_document_id']);
                    $payslips = app(EmployeePayslipService::class)->generateForPayrollDocument($document);

                    Notification::make()
                        ->title('Payslips generated')
                        ->body($payslips->count().' payslip(s) are ready.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
