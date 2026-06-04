<?php

namespace App\Filament\Resources\VendorPostingGroups\Pages;

use App\Filament\Resources\VendorPostingGroups\VendorPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorPostingGroups extends ListRecords
{
    protected static string $resource = VendorPostingGroupResource::class;

    protected static ?string $title = 'Vendor Posting Groups';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
