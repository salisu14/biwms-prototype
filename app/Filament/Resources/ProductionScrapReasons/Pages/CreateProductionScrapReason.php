<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionScrapReasons\Pages;

use App\Filament\Resources\ProductionScrapReasons\ProductionScrapReasonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionScrapReason extends CreateRecord
{
    protected static string $resource = ProductionScrapReasonResource::class;
}
