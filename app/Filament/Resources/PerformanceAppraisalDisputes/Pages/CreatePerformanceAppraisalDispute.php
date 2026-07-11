<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes\Pages;

use App\Filament\Resources\PerformanceAppraisalDisputes\PerformanceAppraisalDisputeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceAppraisalDispute extends CreateRecord
{
    protected static string $resource = PerformanceAppraisalDisputeResource::class;
}
