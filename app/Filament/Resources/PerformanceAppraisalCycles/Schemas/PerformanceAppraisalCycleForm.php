<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Schemas;

use App\Models\PerformanceAppraisalCycle;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalCycleForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceAppraisalCycle::class);
    }
}
