<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings\Schemas;

use App\Models\RecruitmentApplicationScreening;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentApplicationScreeningInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentApplicationScreening::class);
    }
}
