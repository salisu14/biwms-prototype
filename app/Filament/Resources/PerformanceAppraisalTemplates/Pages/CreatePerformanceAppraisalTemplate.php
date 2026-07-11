<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Pages;

use App\Filament\Resources\PerformanceAppraisalTemplates\PerformanceAppraisalTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceAppraisalTemplate extends CreateRecord
{
    protected static string $resource = PerformanceAppraisalTemplateResource::class;
}
