<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers\Pages;

use App\Filament\Resources\RecruitmentOffers\RecruitmentOfferResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentOffer extends CreateRecord
{
    protected static string $resource = RecruitmentOfferResource::class;
}
