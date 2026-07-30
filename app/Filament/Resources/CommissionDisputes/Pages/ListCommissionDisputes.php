<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionDisputes\Pages;

use App\Filament\Resources\CommissionDisputes\CommissionDisputeResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionDisputes extends ListRecords
{
    protected static string $resource = CommissionDisputeResource::class;
}
