<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CommissionPaymentBatches\Pages;

use App\Filament\Resources\CommissionPaymentBatches\Pages\ViewCommissionPaymentBatch as BaseViewCommissionPaymentBatch;
use App\Filament\Sales\Resources\CommissionPaymentBatches\CommissionPaymentBatchResource;

class ViewCommissionPaymentBatch extends BaseViewCommissionPaymentBatch
{
    protected static string $resource = CommissionPaymentBatchResource::class;
}
