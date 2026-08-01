<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CommissionPaymentBatches;

use App\Filament\Resources\CommissionPaymentBatches\CommissionPaymentBatchResource as BaseCommissionPaymentBatchResource;
use App\Filament\Sales\Resources\CommissionPaymentBatches\Pages\CreateCommissionPaymentBatch;
use App\Filament\Sales\Resources\CommissionPaymentBatches\Pages\ListCommissionPaymentBatches;
use App\Filament\Sales\Resources\CommissionPaymentBatches\Pages\ViewCommissionPaymentBatch;

class CommissionPaymentBatchResource extends BaseCommissionPaymentBatchResource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Referral Commissions';

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionPaymentBatches::route('/'),
            'create' => CreateCommissionPaymentBatch::route('/create'),
            'view' => ViewCommissionPaymentBatch::route('/{record}'),
        ];
    }
}
