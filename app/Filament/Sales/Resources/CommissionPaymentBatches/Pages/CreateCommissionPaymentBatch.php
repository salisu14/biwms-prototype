<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CommissionPaymentBatches\Pages;

use App\Filament\Resources\CommissionPaymentBatches\Pages\CreateCommissionPaymentBatch as BaseCreateCommissionPaymentBatch;
use App\Filament\Sales\Resources\CommissionPaymentBatches\CommissionPaymentBatchResource;

class CreateCommissionPaymentBatch extends BaseCreateCommissionPaymentBatch
{
    protected static string $resource = CommissionPaymentBatchResource::class;
}
