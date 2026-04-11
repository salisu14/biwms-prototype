<?php

namespace App\Services\Sales;

use App\Services\Inventory\InventoryValuationReportService;
use Illuminate\Support\Facades\DB;

class ProfitLossService
{
    public function generate($filters): array
    {
        $inventory = app(InventoryValuationReportService::class)
            ->generate($filters['start'], $filters['end'], $filters);

        $cogs = $inventory->sum('sales_value');

        $revenue = DB::table('gl_entries')
            ->where('account_type', 'income')
            ->sum('credit');

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $revenue - $cogs,
        ];
    }
}
