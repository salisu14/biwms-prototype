<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatchLines\Schemas;

use App\Models\AttendancePayrollReviewBatchLine;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class AttendancePayrollReviewBatchLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, AttendancePayrollReviewBatchLine::class);
    }
}
