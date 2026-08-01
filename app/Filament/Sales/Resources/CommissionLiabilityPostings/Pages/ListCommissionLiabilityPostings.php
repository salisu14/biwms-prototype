<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CommissionLiabilityPostings\Pages;

use App\Filament\Resources\CommissionLiabilityPostings\Pages\ListCommissionLiabilityPostings as BaseListCommissionLiabilityPostings;
use App\Filament\Sales\Resources\CommissionLiabilityPostings\CommissionLiabilityPostingResource;

class ListCommissionLiabilityPostings extends BaseListCommissionLiabilityPostings
{
    protected static string $resource = CommissionLiabilityPostingResource::class;
}
