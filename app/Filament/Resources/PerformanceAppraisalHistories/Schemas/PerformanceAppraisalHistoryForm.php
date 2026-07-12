<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Schemas;

use App\Models\PerformanceAppraisalHistory;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceAppraisalHistory::class);
    }
}
