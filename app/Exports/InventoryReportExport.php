<?php

namespace App\Exports;

use App\Services\Inventory\InventoryValuationReportService;
use Maatwebsite\Excel\Concerns\FromCollection;

class InventoryReportExport implements FromCollection
{
    public function __construct(public $filters) {}

    public function collection()
    {
        return app(InventoryValuationReportService::class)
            ->generate(
                $this->filters['start_date'],
                $this->filters['end_date']
            );
    }

//    public function collection()
//    {
//        return app(InventoryValuationReportService::class)
//            ->generate(request('start'), request('end'));
//    }
}
