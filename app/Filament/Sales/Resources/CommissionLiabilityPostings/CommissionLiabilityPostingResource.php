<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\CommissionLiabilityPostings;

use App\Filament\Resources\CommissionLiabilityPostings\CommissionLiabilityPostingResource as BaseCommissionLiabilityPostingResource;
use App\Filament\Sales\Resources\CommissionLiabilityPostings\Pages\ListCommissionLiabilityPostings;
use App\Filament\Sales\Resources\CommissionLiabilityPostings\Pages\ViewCommissionLiabilityPosting;

class CommissionLiabilityPostingResource extends BaseCommissionLiabilityPostingResource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Referral Commissions';

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionLiabilityPostings::route('/'),
            'view' => ViewCommissionLiabilityPosting::route('/{record}'),
        ];
    }
}
