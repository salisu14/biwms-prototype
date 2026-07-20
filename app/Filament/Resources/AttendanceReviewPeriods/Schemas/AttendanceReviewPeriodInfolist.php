<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods\Schemas;

use App\Models\AttendanceReviewPeriod;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendanceReviewPeriodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, AttendanceReviewPeriod::class);
    }
}
