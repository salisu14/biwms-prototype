<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods\Schemas;

use App\Models\Business;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceReviewPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review Period')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('ATT-2026-07'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('business_id')
                            ->options(fn (): array => class_exists(Business::class) ? Business::query()->orderBy('name')->pluck('name', 'id')->all() : [])
                            ->searchable(),
                        DatePicker::make('date_from')
                            ->required()
                            ->native(false),
                        DatePicker::make('date_to')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('date_from'),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'open' => 'Open',
                                'under_review' => 'Under Review',
                                'approved' => 'Approved',
                                'locked' => 'Locked',
                                'exported' => 'Exported',
                                'reopened' => 'Reopened',
                            ])
                            ->default('open')
                            ->required(),
                        Textarea::make('notes')->columnSpanFull(),
                    ]),
            ]);
    }
}
