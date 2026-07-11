<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies\Pages;

use App\Filament\Resources\PerformanceCompetencies\PerformanceCompetencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceCompetency extends CreateRecord
{
    protected static string $resource = PerformanceCompetencyResource::class;
}
