<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Schemas;

use App\Models\PerformanceAppraisalTemplate;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceAppraisalTemplate::class);
    }
}
