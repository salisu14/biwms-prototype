<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentHistories\Schemas;

use App\Models\RecruitmentHistory;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentHistory::class);
    }
}
