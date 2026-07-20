<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels\Schemas;

use App\Models\RecruitmentInterviewPanel;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentInterviewPanelForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentInterviewPanel::class);
    }
}
