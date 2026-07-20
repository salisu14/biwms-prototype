<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions\Schemas;

use App\Models\EmployeeConfirmationDecision;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class EmployeeConfirmationDecisionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, EmployeeConfirmationDecision::class);
    }
}
