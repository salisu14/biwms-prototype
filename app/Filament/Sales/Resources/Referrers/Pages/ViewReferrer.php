<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\Referrers\Pages;

use App\Filament\Sales\Resources\Referrers\ReferrerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReferrer extends ViewRecord
{
    protected static string $resource = ReferrerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
