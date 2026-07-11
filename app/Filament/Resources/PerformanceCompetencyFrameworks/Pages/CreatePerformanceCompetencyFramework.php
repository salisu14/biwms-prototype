<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Pages;

use App\Filament\Resources\PerformanceCompetencyFrameworks\PerformanceCompetencyFrameworkResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceCompetencyFramework extends CreateRecord
{
    protected static string $resource = PerformanceCompetencyFrameworkResource::class;
}
