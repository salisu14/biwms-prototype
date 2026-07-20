<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers\Schemas;

use App\Models\RecruitmentOffer;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentOffer::class);
    }
}
