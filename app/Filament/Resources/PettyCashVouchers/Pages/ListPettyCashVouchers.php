<?php

namespace App\Filament\Resources\PettyCashVouchers\Pages;

use App\Filament\Resources\PettyCashVouchers\PettyCashVoucherResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPettyCashVouchers extends ListRecords
{
    protected static string $resource = PettyCashVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
