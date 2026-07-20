<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplications\Schemas;

use App\Models\RecruitmentApplication;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentApplication::class);
    }
}
