<?php

namespace App\Filament\Resources\ApprovalTemplates\Pages;

use App\Filament\Resources\ApprovalTemplates\ApprovalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalTemplates extends ListRecords
{
    protected static string $resource = ApprovalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
