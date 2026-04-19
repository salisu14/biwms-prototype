<?php

namespace App\Filament\Resources\ApprovalTemplates\Pages;

use App\Filament\Resources\ApprovalTemplates\ApprovalTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewApprovalTemplate extends ViewRecord
{
    protected static string $resource = ApprovalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
