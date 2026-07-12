<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels\Schemas;

use App\Models\RecruitmentInterviewPanel;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentInterviewPanelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentInterviewPanel::class);
    }
}
