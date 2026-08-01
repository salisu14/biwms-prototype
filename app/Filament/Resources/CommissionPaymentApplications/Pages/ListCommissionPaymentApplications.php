<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentApplications\Pages;

use App\Filament\Resources\CommissionPaymentApplications\CommissionPaymentApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionPaymentApplications extends ListRecords
{
    protected static string $resource = CommissionPaymentApplicationResource::class;
}
