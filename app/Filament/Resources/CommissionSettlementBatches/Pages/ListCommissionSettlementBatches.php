<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionSettlementBatches\Pages;

use App\Filament\Resources\CommissionSettlementBatches\CommissionSettlementBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionSettlementBatches extends ListRecords
{
    protected static string $resource = CommissionSettlementBatchResource::class;
}
