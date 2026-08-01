<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionLiabilityPostings\Pages;

use App\Filament\Resources\CommissionLiabilityPostings\CommissionLiabilityPostingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommissionLiabilityPosting extends ViewRecord
{
    protected static string $resource = CommissionLiabilityPostingResource::class;
}
