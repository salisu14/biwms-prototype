<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers\Pages;

use App\Filament\Resources\RecruitmentOffers\RecruitmentOfferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentOffer extends ViewRecord
{
    protected static string $resource = RecruitmentOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
