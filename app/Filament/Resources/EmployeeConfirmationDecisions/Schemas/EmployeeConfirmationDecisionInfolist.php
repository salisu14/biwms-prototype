<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions\Schemas;

use App\Models\EmployeeConfirmationDecision;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class EmployeeConfirmationDecisionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, EmployeeConfirmationDecision::class);
    }
}
