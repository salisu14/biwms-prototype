<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionOperationExecutions\Pages;

use App\Filament\Resources\ProductionOperationExecutions\ProductionOperationExecutionResource;
use Filament\Resources\Pages\ListRecords;

class ListProductionOperationExecutions extends ListRecords
{
    protected static string $resource = ProductionOperationExecutionResource::class;
}
