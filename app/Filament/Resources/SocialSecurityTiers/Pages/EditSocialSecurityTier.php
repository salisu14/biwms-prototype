<?php

namespace App\Filament\Resources\SocialSecurityTiers\Pages;

use App\Filament\Resources\SocialSecurityTiers\SocialSecurityTierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialSecurityTier extends EditRecord
{
    protected static string $resource = SocialSecurityTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
