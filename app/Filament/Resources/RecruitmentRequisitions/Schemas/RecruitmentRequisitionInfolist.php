<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentRequisitions\Schemas;

use App\Models\RecruitmentRequisition;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentRequisitionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentRequisition::class);
    }
}
