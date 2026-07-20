<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentRequisitions\Schemas;

use App\Models\RecruitmentRequisition;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentRequisitionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentRequisition::class);
    }
}
