<?php

namespace App\Filament\Exports;

use App\Models\ProcurementRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ProcurementRequestExporter extends Exporter
{
    protected static ?string $model = ProcurementRequest::class;

    // FIX: Use the relationship method names defined in your Model
    public static function getEagerLoadRelationships(): array
    {
        return [
            'department',
            'requester',   // Changed from 'requested_by'
            'approver',    // Changed from 'approved_by'
        ];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('department.name')
                ->label('Department Name'),
            ExportColumn::make('requested_by')
                ->label('Requested By'),
            ExportColumn::make('approved_by'),
            ExportColumn::make('approved_at'),
            ExportColumn::make('status'),
            ExportColumn::make('total_estimated_cost'),
            ExportColumn::make('justification'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your procurement request export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
