<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceCorrectionRequests\Schemas;

use App\Models\AttendanceCorrectionRequest;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendanceCorrectionRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, AttendanceCorrectionRequest::class);
    }
}
