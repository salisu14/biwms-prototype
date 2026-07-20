<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatches\Schemas;

use App\Models\AttendancePayrollReviewBatch;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendancePayrollReviewBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::form($schema, AttendancePayrollReviewBatch::class);
    }
}
