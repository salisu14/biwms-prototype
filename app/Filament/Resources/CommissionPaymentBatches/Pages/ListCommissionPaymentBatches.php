<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentBatches\Pages;

use App\Filament\Resources\CommissionPaymentBatches\CommissionPaymentBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionPaymentBatches extends ListRecords
{
    protected static string $resource = CommissionPaymentBatchResource::class;
}
