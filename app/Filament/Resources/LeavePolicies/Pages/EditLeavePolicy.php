<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeavePolicies\Pages;

use App\Filament\Resources\LeavePolicies\LeavePolicyResource;
use Filament\Resources\Pages\EditRecord;

class EditLeavePolicy extends EditRecord
{
    protected static string $resource = LeavePolicyResource::class;
}
