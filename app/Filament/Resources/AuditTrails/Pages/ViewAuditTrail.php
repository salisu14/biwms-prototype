<?php

namespace App\Filament\Resources\AuditTrails\Pages;

use App\Filament\Resources\AuditTrails\AuditTrailResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditTrail extends ViewRecord
{
    protected static string $resource = AuditTrailResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
