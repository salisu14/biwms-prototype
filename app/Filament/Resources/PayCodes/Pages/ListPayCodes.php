<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayCodes\Pages;

use App\Filament\Resources\PayCodes\PayCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayCodes extends ListRecords
{
    protected static string $resource = PayCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
