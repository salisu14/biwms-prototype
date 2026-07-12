<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Schemas;

use App\Models\PerformanceAppraisalHistory;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, PerformanceAppraisalHistory::class);
    }
}
