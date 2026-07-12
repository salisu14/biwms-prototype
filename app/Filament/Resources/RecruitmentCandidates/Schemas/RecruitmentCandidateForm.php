<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentCandidates\Schemas;

use App\Models\RecruitmentCandidate;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentCandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, RecruitmentCandidate::class);
    }
}
