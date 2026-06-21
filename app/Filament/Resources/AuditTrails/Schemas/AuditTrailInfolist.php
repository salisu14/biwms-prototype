<?php

namespace App\Filament\Resources\AuditTrails\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditTrailInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('event_type')->badge(),
                                TextEntry::make('action')->badge(),
                                TextEntry::make('occurred_at')->dateTime('d/m/Y H:i:s'),
                            ]),
                        TextEntry::make('description')->columnSpanFull(),
                    ]),
                Section::make('Document')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('document_type')->placeholder('-'),
                                TextEntry::make('document_no')->placeholder('-')->copyable(),
                                TextEntry::make('user.name')->label('User')->placeholder('-'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('auditable_type')->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)->placeholder('-'),
                                TextEntry::make('auditable_id')->placeholder('-'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('source_type')->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)->placeholder('-'),
                                TextEntry::make('source_id')->placeholder('-'),
                            ]),
                    ]),
                Section::make('Payload')
                    ->schema([
                        TextEntry::make('old_values')
                            ->formatStateUsing(fn (?array $state): ?string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null)
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('new_values')
                            ->formatStateUsing(fn (?array $state): ?string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null)
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('metadata')
                            ->formatStateUsing(fn (?array $state): ?string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null)
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Request')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('ip_address')->placeholder('-'),
                                TextEntry::make('user_agent')->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
