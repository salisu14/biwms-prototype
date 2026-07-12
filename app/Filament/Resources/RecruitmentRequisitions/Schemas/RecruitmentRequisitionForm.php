<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentRequisitions\Schemas;

use App\Models\RecruitmentRequisition;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentRequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, RecruitmentRequisition::class);
    }
}
