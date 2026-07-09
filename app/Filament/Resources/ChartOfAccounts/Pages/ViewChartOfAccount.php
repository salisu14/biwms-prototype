<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChartOfAccounts\Pages;

use App\Filament\Resources\ChartOfAccounts\ChartOfAccountResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewChartOfAccount extends ViewRecord
{
    protected static string $resource = ChartOfAccountResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
