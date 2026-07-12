<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks\Schemas;

use App\Models\RecruitmentPreEmploymentCheck;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentPreEmploymentCheckInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentPreEmploymentCheck::class);
    }
}
