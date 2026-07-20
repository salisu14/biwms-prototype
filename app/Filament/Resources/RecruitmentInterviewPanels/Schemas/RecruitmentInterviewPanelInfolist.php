<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels\Schemas;

use App\Models\RecruitmentInterviewPanel;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentInterviewPanelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentInterviewPanel::class);
    }
}
