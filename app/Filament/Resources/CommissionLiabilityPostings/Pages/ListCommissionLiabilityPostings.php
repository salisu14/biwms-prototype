<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionLiabilityPostings\Pages;

use App\Filament\Resources\CommissionLiabilityPostings\CommissionLiabilityPostingResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionLiabilityPostings extends ListRecords
{
    protected static string $resource = CommissionLiabilityPostingResource::class;
}
