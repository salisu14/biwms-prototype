<?php

namespace App\Filament\Resources\AuditTrails\Pages;

use App\Filament\Resources\AuditTrails\AuditTrailResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditTrails extends ListRecords
{
    protected static string $resource = AuditTrailResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
