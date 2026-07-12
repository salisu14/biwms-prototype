<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories\Pages;

use App\Filament\Resources\WorkforceRosterHistories\WorkforceRosterHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceRosterHistories extends ListRecords
{
    protected static string $resource = WorkforceRosterHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
