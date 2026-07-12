<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes\Schemas;

use App\Models\PerformanceAppraisalDispute;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalDisputeForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceAppraisalDispute::class);
    }
}
