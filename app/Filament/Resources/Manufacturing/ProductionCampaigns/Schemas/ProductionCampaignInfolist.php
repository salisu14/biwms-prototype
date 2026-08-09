<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionCampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')->weight('bold'),
                        TextEntry::make('name'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('workCenter.name')->label('Work Center'),
                        TextEntry::make('grouping_rule'),
                        TextEntry::make('grouping_key'),
                        TextEntry::make('planned_start_at')->dateTime(),
                        TextEntry::make('planned_end_at')->dateTime(),
                        TextEntry::make('setup_reduction_percent')->suffix('%'),
                        TextEntry::make('orders_count')->counts('orders')->label('Orders'),
                    ]),
            ]);
    }
}
