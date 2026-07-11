<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules\Pages;

use App\Filament\Resources\WorkforceSchedulingRules\WorkforceSchedulingRuleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceSchedulingRule extends ViewRecord
{
    protected static string $resource = WorkforceSchedulingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
