<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollRules\Schemas;

use App\Models\AttendancePayrollRule;
use App\Models\AttendanceReviewItem;
use App\Models\PayCode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendancePayrollRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('code')->required()->maxLength(255),
                        TextInput::make('name')->required()->maxLength(255),
                        Select::make('attendance_issue_type')
                            ->options([
                                AttendanceReviewItem::ISSUE_APPROVED_OVERTIME => 'Approved Overtime',
                                AttendanceReviewItem::ISSUE_UNPAID_ABSENCE => 'Unpaid Absence',
                                AttendanceReviewItem::ISSUE_LATE => 'Lateness',
                                AttendanceReviewItem::ISSUE_EARLY_DEPARTURE => 'Early Departure',
                            ])
                            ->required(),
                        Select::make('impact_type')
                            ->options([
                                AttendancePayrollRule::IMPACT_EARNING => 'Earning',
                                AttendancePayrollRule::IMPACT_DEDUCTION => 'Deduction',
                                AttendancePayrollRule::IMPACT_INFORMATIONAL => 'Informational',
                            ])
                            ->required(),
                        Select::make('calculation_method')
                            ->options([
                                'hourly_rate' => 'Hourly Rate',
                                'daily_rate' => 'Daily Rate',
                                'fixed_amount' => 'Fixed Amount',
                                'manual' => 'Manual',
                            ])
                            ->required(),
                        TextInput::make('rate')->numeric()->minValue(0),
                        Select::make('earning_component_id')
                            ->options(fn (): array => PayCode::query()->orderBy('code')->pluck('code', 'id')->all())
                            ->searchable(),
                        Select::make('deduction_component_id')
                            ->options(fn (): array => PayCode::query()->orderBy('code')->pluck('code', 'id')->all())
                            ->searchable(),
                        DatePicker::make('effective_from')->required()->native(false),
                        DatePicker::make('effective_to')->native(false),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
