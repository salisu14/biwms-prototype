<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentLines\Pages;

use App\Filament\Resources\CommissionPaymentLines\CommissionPaymentLineResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommissionPaymentLine extends ViewRecord
{
    protected static string $resource = CommissionPaymentLineResource::class;
}
