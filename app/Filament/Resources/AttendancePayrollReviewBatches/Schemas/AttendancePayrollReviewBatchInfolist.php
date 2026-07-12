<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatches\Schemas;

use App\Models\AttendancePayrollReviewBatch;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class AttendancePayrollReviewBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, AttendancePayrollReviewBatch::class);
    }
}
