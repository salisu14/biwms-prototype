<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplications\Schemas;

use App\Models\RecruitmentApplication;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentApplication::class);
    }
}
