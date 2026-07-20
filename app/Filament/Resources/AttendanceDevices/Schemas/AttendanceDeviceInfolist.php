<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceDevices\Schemas;

use App\Models\AttendanceDevice;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class AttendanceDeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, AttendanceDevice::class);
    }
}
