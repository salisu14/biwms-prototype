<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeePayslips\Pages;

use App\Filament\Resources\EmployeePayslips\EmployeePayslipResource;
use App\Models\EmployeePayslip;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeePayslip extends ViewRecord
{
    protected static string $resource = EmployeePayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->icon('heroicon-o-eye')
                ->url(fn (EmployeePayslip $record): string => route('employee-payslips.preview', $record))
                ->openUrlInNewTab(),
            Action::make('print')
                ->icon('heroicon-o-printer')
                ->url(fn (EmployeePayslip $record): string => route('employee-payslips.print', $record))
                ->openUrlInNewTab(),
            Action::make('download')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (EmployeePayslip $record): string => route('employee-payslips.download', $record))
                ->openUrlInNewTab(),
        ];
    }
}
