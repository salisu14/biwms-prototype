<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions\Schemas;

use App\Models\PerformanceAppraisalModerationSession;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalModerationSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::form($schema, PerformanceAppraisalModerationSession::class);
    }
}
