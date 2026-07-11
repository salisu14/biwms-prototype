<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules\Pages;

use App\Filament\Resources\WorkforceSchedulingRules\WorkforceSchedulingRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceSchedulingRule extends EditRecord
{
    protected static string $resource = WorkforceSchedulingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
