<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollRules\Schemas;

use App\Models\AttendancePayrollRule;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendancePayrollRuleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, AttendancePayrollRule::class);
    }
}
