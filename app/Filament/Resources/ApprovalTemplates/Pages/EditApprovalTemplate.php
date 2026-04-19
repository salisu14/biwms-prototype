<?php

namespace App\Filament\Resources\ApprovalTemplates\Pages;

use App\Filament\Resources\ApprovalTemplates\ApprovalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditApprovalTemplate extends EditRecord
{
    protected static string $resource = ApprovalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
