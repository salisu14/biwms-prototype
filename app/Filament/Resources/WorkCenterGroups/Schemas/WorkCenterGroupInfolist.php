<?php

namespace App\Filament\Resources\WorkCenterGroups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkCenterGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Identification')
                    ->description('Primary classification details for this production group.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('code')
                                ->label('Group Code')
                                ->weight('bold')
                                ->icon('heroicon-m-tag')
                                ->copyable(),

                            TextEntry::make('name')
                                ->label('Group Name')
                                ->weight('bold'),
                        ]),
                    ]),

                Section::make('Work Center Statistics')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('work_centers_count')
                                ->label('Assigned Work Centers')
                                ->state(fn ($record) => $record->workCenters()->count())
                                ->badge()
                                ->color('info')
                                ->icon('heroicon-m-cpu-chip'),
                        ]),
                    ]),

                Section::make('System Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->label('Created Date')
                                ->dateTime()
                                ->placeholder('-'),

                            TextEntry::make('updated_at')
                                ->label('Last Modified')
                                ->dateTime()
                                ->placeholder('-'),
                        ]),
                    ]),
            ]);
    }
}
