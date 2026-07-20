<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems\Schemas;

use App\Models\AttendanceReviewItem;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendanceReviewItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, AttendanceReviewItem::class);
    }
}
