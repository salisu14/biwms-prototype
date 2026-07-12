<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WorkforceRosterRoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Identification')
                    ->icon('heroicon-o-identification')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Role Code')
                            ->icon('heroicon-o-hashtag')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->copyable(),

                        TextEntry::make('name')
                            ->label('Role Name')
                            ->icon('heroicon-o-user-circle')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('business.name')
                            ->label('Business')
                            ->icon('heroicon-o-building-office')
                            ->color('primary'),
                    ]),

                Section::make('Description')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('description')
                            ->label('Role Description')
                            ->markdown()
                            ->prose()
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Assignment')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('department.name')
                            ->label('Department')
                            ->icon('heroicon-o-users')
                            ->placeholder('Not assigned to a department')
                            ->weight('font-medium'),

                        TextEntry::make('workCenter.name')
                            ->label('Work Center')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->placeholder('Not assigned to a work center')
                            ->weight('font-medium'),
                    ]),

                Section::make('Status & Flags')
                    ->icon('heroicon-o-flag')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        //                            ->trueLabel('Active — available for roster assignments')
                        //                            ->falseLabel('Inactive — hidden from roster assignments'),

                        IconEntry::make('is_critical')
                            ->label('Critical Role')
                            ->boolean()
                            ->trueIcon('heroicon-o-exclamation-triangle')
                            ->falseIcon('heroicon-o-minus')
                            ->trueColor('danger')
                            ->falseColor('gray'),
                        //                            ->trueLabel('Critical — must always be staffed, triggers alerts if vacant')
                        //                            ->falseLabel('Non-critical — standard staffing rules apply'),
                    ]),

                Section::make('Audit Trail')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M d, Y H:i')
                            ->icon('heroicon-o-plus-circle'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M d, Y H:i')
                            ->icon('heroicon-o-arrow-path'),
                    ]),
            ]);
    }
}
