<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes\Schemas;

use App\Models\PerformanceAppraisalDispute;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalDisputeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceAppraisalDispute::class);
    }
}
