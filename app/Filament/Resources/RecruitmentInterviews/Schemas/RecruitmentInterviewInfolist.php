<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviews\Schemas;

use App\Models\RecruitmentInterview;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentInterviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentInterview::class);
    }
}
