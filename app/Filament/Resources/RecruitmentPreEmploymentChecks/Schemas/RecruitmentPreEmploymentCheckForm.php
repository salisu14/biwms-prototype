<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks\Schemas;

use App\Models\RecruitmentPreEmploymentCheck;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentPreEmploymentCheckForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentPreEmploymentCheck::class);
    }
}
