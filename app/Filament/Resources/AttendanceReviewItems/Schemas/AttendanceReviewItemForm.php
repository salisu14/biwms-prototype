<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems\Schemas;

use App\Models\AttendanceReviewItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceReviewItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review Decision')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('review_status')
                            ->options([
                                AttendanceReviewItem::STATUS_PENDING => 'Pending',
                                AttendanceReviewItem::STATUS_ACKNOWLEDGED => 'Acknowledged',
                                AttendanceReviewItem::STATUS_MANAGER_REVIEWED => 'Manager Reviewed',
                                AttendanceReviewItem::STATUS_HR_REVIEWED => 'HR Reviewed',
                                AttendanceReviewItem::STATUS_RESOLVED => 'Resolved',
                                AttendanceReviewItem::STATUS_WAIVED => 'Waived',
                                AttendanceReviewItem::STATUS_ESCALATED => 'Escalated',
                            ])
                            ->required(),
                        Select::make('resolution_type')
                            ->options([
                                'approved' => 'Approved',
                                'waived' => 'Waived',
                                'corrected' => 'Corrected',
                                'payroll_adjustment' => 'Payroll Adjustment',
                            ]),
                        Textarea::make('resolution_notes')->columnSpanFull(),
                    ]),
            ]);
    }
}
