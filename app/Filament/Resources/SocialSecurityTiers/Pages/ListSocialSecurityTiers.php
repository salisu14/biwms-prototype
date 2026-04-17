<?php

namespace App\Filament\Resources\SocialSecurityTiers\Pages;

use App\Filament\Resources\SocialSecurityTiers\SocialSecurityTierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocialSecurityTiers extends ListRecords
{
    protected static string $resource = SocialSecurityTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
