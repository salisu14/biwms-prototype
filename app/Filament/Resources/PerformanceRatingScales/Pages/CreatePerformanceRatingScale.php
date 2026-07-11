<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Pages;

use App\Filament\Resources\PerformanceRatingScales\PerformanceRatingScaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceRatingScale extends CreateRecord
{
    protected static string $resource = PerformanceRatingScaleResource::class;
}
