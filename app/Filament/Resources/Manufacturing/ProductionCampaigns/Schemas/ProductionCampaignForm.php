<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns\Schemas;

use App\Enums\ProductionCampaignStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn (): string => 'CAMP-'.now()->format('YmdHis')),
                        TextInput::make('name')->required(),
                        Select::make('status')
                            ->options([
                                ProductionCampaignStatus::Draft->value => 'Draft',
                                ProductionCampaignStatus::Suggested->value => 'Suggested',
                                ProductionCampaignStatus::Approved->value => 'Approved',
                                ProductionCampaignStatus::InProgress->value => 'In Progress',
                                ProductionCampaignStatus::Completed->value => 'Completed',
                                ProductionCampaignStatus::Cancelled->value => 'Cancelled',
                            ])
                            ->default(ProductionCampaignStatus::Draft->value)
                            ->required(),
                        Select::make('work_center_id')
                            ->relationship('workCenter', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('grouping_rule')
                            ->default('planner_selected')
                            ->required(),
                        TextInput::make('grouping_key'),
                        DateTimePicker::make('planned_start_at')->seconds(false),
                        DateTimePicker::make('planned_end_at')->seconds(false),
                        TextInput::make('setup_reduction_percent')
                            ->numeric()
                            ->suffix('%')
                            ->default(0),
                        Textarea::make('changeover_notes')->columnSpanFull(),
                    ]),
            ]);
    }
}
