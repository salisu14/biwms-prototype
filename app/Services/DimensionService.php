<?php

namespace App\Services;

use App\Models\DefaultDimension;
use Illuminate\Database\Eloquent\Model;

class DimensionService
{
    /**
     * Map common Laravel table names to BC Table IDs.
     */
    public function resolveTableId(string $tableId): string
    {
        $map = [
            'customers' => '18',
            'vendors' => '23',
            'items' => '27',
            'employees' => '5200',
        ];

        return $map[$tableId] ?? $tableId;
    }

    /**
     * Assign a default dimension to a master data record.
     */
    public function assignDefaultDimension(Model $record, string $tableId, string $dimCode, ?string $valCode): void
    {
        $tableId = $this->resolveTableId($tableId);

        // Get the 'no' or 'number' from the record (BC convention)
        $no = $record->employee_number ?? $record->customer_number ?? $record->vendor_code ?? $record->item_number ?? $record->id;

        if ($valCode) {
            DefaultDimension::updateOrCreate(
                [
                    'table_id' => $tableId,
                    'no' => $no,
                    'dimension_code' => $dimCode,
                ],
                [
                    'dimension_value_code' => $valCode,
                ]
            );
        } else {
            DefaultDimension::where([
                'table_id' => $tableId,
                'no' => $no,
                'dimension_code' => $dimCode,
            ])->delete();
        }
    }

    /**
     * Bulk sync dimensions for an entity.
     */
    public function syncDimensions(Model $record, string $tableId, array $dimensions): void
    {
        foreach ($dimensions as $code => $value) {
            $this->assignDefaultDimension($record, $tableId, $code, $value);
        }
    }
}
