<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers\Pages;

use App\Filament\Resources\RecruitmentOffers\RecruitmentOfferResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentOffer extends EditRecord
{
    protected static string $resource = RecruitmentOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
