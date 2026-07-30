<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionReviewBatches\Pages;

use App\Filament\Resources\CommissionReviewBatches\CommissionReviewBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionReviewBatches extends ListRecords
{
    protected static string $resource = CommissionReviewBatchResource::class;
}
