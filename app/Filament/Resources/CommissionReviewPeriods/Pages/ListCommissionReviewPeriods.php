<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionReviewPeriods\Pages;

use App\Filament\Resources\CommissionReviewPeriods\CommissionReviewPeriodResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionReviewPeriods extends ListRecords
{
    protected static string $resource = CommissionReviewPeriodResource::class;
}
