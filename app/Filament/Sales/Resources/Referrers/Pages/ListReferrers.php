<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\Referrers\Pages;

use App\Filament\Sales\Resources\Referrers\ReferrerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferrers extends ListRecords
{
    protected static string $resource = ReferrerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
