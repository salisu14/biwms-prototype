<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Schemas;

use App\Models\PerformanceAppraisalHistory;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceAppraisalHistory::class);
    }
}
