<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentJobPostings\Schemas;

use App\Models\RecruitmentJobPosting;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentJobPostingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentJobPosting::class);
    }
}
