<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatchLines\Schemas;

use App\Models\AttendancePayrollReviewBatchLine;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendancePayrollReviewBatchLineInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, AttendancePayrollReviewBatchLine::class);
    }
}
