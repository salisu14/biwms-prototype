<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CommissionPaymentBatches\Pages;

use App\Filament\Resources\CommissionPaymentBatches\Pages\ListCommissionPaymentBatches as BaseListCommissionPaymentBatches;
use App\Filament\Sales\Resources\CommissionPaymentBatches\CommissionPaymentBatchResource;

class ListCommissionPaymentBatches extends BaseListCommissionPaymentBatches
{
    protected static string $resource = CommissionPaymentBatchResource::class;
}
