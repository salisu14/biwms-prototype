<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings\Schemas;

use App\Models\RecruitmentApplicationScreening;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentApplicationScreeningForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentApplicationScreening::class);
    }
}
