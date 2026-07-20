<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentHistories\Schemas;

use App\Models\RecruitmentHistory;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentHistory::class);
    }
}
