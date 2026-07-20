<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentJobPostings\Schemas;

use App\Models\RecruitmentJobPosting;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentJobPostingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentJobPosting::class);
    }
}
