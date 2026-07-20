<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisals\Schemas;

use App\Models\PerformanceAppraisal;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalForm
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::form($schema, PerformanceAppraisal::class);
    }
}
