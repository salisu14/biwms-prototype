<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Pages;

use App\Filament\Resources\PerformancePositionCompetencies\PerformancePositionCompetencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformancePositionCompetency extends CreateRecord
{
    protected static string $resource = PerformancePositionCompetencyResource::class;
}
