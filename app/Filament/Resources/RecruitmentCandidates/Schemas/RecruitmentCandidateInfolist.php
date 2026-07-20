<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentCandidates\Schemas;

use App\Models\RecruitmentCandidate;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentCandidateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentCandidate::class);
    }
}
