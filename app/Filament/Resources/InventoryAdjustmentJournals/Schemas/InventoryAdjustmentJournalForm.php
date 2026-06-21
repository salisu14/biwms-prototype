<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\Schemas;

use App\Models\ReasonCode;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InventoryAdjustmentJournalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)->schema([
                    Section::make('General Information')
                        ->description('Primary identification and batch details for this adjustment.')
                        ->columnSpan(12)
                        ->columns(2)
                        ->schema([
                            TextInput::make('journal_batch_name')
                                ->label('Batch Name')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->disabled(fn ($record) => $record?->status === 'Posted')
                                ->placeholder('e.g., ADJ-MAY-2024')
                                ->prefixIcon('heroicon-m-tag')
                                ->helperText('Unique identifier for this adjustment batch.'),

                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'Open' => 'Open',
                                    'Released' => 'Released',
                                    'Posted' => 'Posted',
                                ])
                                ->default('Open')
                                ->required()
                                ->selectablePlaceholder(false)
                                ->disabled(fn ($record) => $record?->status === 'Posted')
                                ->live()
                                ->native(false)
                                ->prefixIcon('heroicon-m-check-circle'),

                            TextInput::make('description')
                                ->label('Batch Description')
                                ->maxLength(255)
                                ->columnSpanFull()
                                ->placeholder('Provide context for these inventory changes...'),
                        ]),

                    Section::make('Inventory Context')
                        ->description('Specify the warehouse location and the reason for this adjustment.')
                        ->columnSpan(12)
                        ->columns(2)
                        ->schema([
                            Select::make('location_code')
                                ->label('Default Location')
                                ->relationship('location', 'name')
                                ->preload()
                                ->searchable()
                                ->required()
                                ->disabled(fn ($record) => $record?->status === 'Posted')
                                ->prefixIcon('heroicon-m-map-pin')
                                ->helperText('The warehouse location where items will be adjusted.')
                                ->columnSpan(1),

                            Select::make('reason_code')
                                ->label('Reason Code')
                                ->relationship('reasonCode', 'code', fn ($query) => $query->where('blocked', false))
                                ->getOptionLabelFromRecordUsing(fn (ReasonCode $record) => "{$record->code} - {$record->description}")
                                ->preload()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($state) {
                                        $reason = ReasonCode::where('code', $state)->first();
                                        if ($reason?->default_location_code) {
                                            $set('location_code', $reason->default_location_code);
                                        }
                                    }
                                })
                                ->disabled(fn ($record) => $record?->status === 'Posted')
                                ->prefixIcon('heroicon-m-question-mark-circle')
                                ->helperText('The business reason for the adjustment. Selecting this may suggest a default location.')
                                ->columnSpan(1),
                        ]),

                    Section::make('Scheduling & Assignment')
                        ->columnSpan(8)
                        ->columns(2)
                        ->schema([
                            Group::make([
                                DatePicker::make('posting_date')
                                    ->label('Posting Date')
                                    ->required()
                                    ->default(now())
                                    ->disabled(fn ($record) => $record?->status === 'Posted')
                                    ->prefixIcon('heroicon-m-calendar'),

                                DatePicker::make('document_date')
                                    ->label('Document Date')
                                    ->default(now())
                                    ->disabled(fn ($record) => $record?->status === 'Posted')
                                    ->prefixIcon('heroicon-m-document-text'),
                            ])->columns(2)->columnSpanFull(),

                            Select::make('assigned_user_id')
                                ->label('Assigned User')
                                ->relationship('assignedUser', 'name')
                                ->preload()
                                ->searchable()
                                ->disabled(fn ($record) => $record?->status === 'Posted')
                                ->prefixIcon('heroicon-m-user')
                                ->columnSpanFull(),
                        ]),

                    Section::make('Audit Trail')
                        ->description('Read-only information captured upon posting.')
                        ->columnSpan(4)
                        ->visible(fn ($record) => $record?->status === 'Posted')
                        ->schema([
                            TextInput::make('posted_by')
                                ->label('Posted By')
                                ->formatStateUsing(fn ($state) => User::find($state)?->name ?? $state)
                                ->disabled()
                                ->prefixIcon('heroicon-m-user-circle'),

                            DateTimePicker::make('posted_at')
                                ->label('Posted Timestamp')
                                ->disabled()
                                ->prefixIcon('heroicon-m-clock'),
                        ]),
                ]),
            ]);
    }
}
