<?php

namespace App\Filament\Exports;

use App\Models\Employee;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class EmployeeExporter extends Exporter
{
    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('first_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('middle_name'),
            ExportColumn::make('user.name'),
            ExportColumn::make('department.name'),
            ExportColumn::make('employment_type'),
            ExportColumn::make('rank'),
            ExportColumn::make('designation'),
            ExportColumn::make('staff_id'),
            ExportColumn::make('specialization'),
            ExportColumn::make('date_of_first_appointment'),
            ExportColumn::make('experience'),
            ExportColumn::make('salary'),
            ExportColumn::make('gender'),
            ExportColumn::make('status'),
            ExportColumn::make('blood_group'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your employee export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
