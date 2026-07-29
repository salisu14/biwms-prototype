<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionScrapReasons\Pages;

use App\Filament\Resources\ProductionScrapReasons\ProductionScrapReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionScrapReasons extends ListRecords
{
    protected static string $resource = ProductionScrapReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
