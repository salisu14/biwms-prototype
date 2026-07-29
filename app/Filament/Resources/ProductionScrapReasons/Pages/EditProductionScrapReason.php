<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionScrapReasons\Pages;

use App\Filament\Resources\ProductionScrapReasons\ProductionScrapReasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionScrapReason extends EditRecord
{
    protected static string $resource = ProductionScrapReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
