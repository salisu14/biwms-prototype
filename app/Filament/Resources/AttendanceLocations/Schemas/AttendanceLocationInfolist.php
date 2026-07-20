<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceLocations\Schemas;

use App\Models\AttendanceLocation;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendanceLocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, AttendanceLocation::class);
    }
}
