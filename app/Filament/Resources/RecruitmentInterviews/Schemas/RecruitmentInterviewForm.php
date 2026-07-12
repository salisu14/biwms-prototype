<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviews\Schemas;

use App\Models\RecruitmentInterview;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentInterviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, RecruitmentInterview::class);
    }
}
