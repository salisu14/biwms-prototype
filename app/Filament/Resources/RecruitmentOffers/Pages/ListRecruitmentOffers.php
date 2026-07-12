<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers\Pages;

use App\Filament\Resources\RecruitmentOffers\RecruitmentOfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentOffers extends ListRecords
{
    protected static string $resource = RecruitmentOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
