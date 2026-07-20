<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes\Tables;

use App\Models\PerformanceAppraisalDispute;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Tables\Table;

class PerformanceAppraisalDisputesTable
{
    public static function configure(Table $table): Table
    {
        return PerformanceResourceSchema::table($table, PerformanceAppraisalDispute::class);
    }
}
