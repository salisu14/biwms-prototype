<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules\Pages;

use App\Filament\Resources\WorkforceSchedulingRules\WorkforceSchedulingRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceSchedulingRule extends CreateRecord
{
    protected static string $resource = WorkforceSchedulingRuleResource::class;
}
