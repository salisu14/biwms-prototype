<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CommissionLiabilityPostings\Pages;

use App\Filament\Resources\CommissionLiabilityPostings\Pages\ViewCommissionLiabilityPosting as BaseViewCommissionLiabilityPosting;
use App\Filament\Sales\Resources\CommissionLiabilityPostings\CommissionLiabilityPostingResource;

class ViewCommissionLiabilityPosting extends BaseViewCommissionLiabilityPosting
{
    protected static string $resource = CommissionLiabilityPostingResource::class;
}
