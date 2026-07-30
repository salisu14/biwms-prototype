<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionReviewBatchLines\Pages;

use App\Filament\Resources\CommissionReviewBatchLines\CommissionReviewBatchLineResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionReviewBatchLines extends ListRecords
{
    protected static string $resource = CommissionReviewBatchLineResource::class;
}
