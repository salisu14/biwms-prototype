<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules\Pages;

use App\Filament\Resources\WorkforceSchedulingRules\WorkforceSchedulingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceSchedulingRules extends ListRecords
{
    protected static string $resource = WorkforceSchedulingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
