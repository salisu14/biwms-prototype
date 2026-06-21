<?php

namespace App\Filament\Resources\ProductionBoms\Pages;

use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionBom extends CreateRecord
{
    protected static string $resource = ProductionBomResource::class;
}
