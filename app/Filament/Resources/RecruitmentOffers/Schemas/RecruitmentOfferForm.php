<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers\Schemas;

use App\Models\RecruitmentOffer;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, RecruitmentOffer::class);
    }
}
