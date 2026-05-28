<?php

namespace App\Filament\Resources\GeneralJournalBatches\Schemas;

use App\Models\ChartOfAccount;
use App\Models\ReasonCode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralJournalBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Details')
                    ->columns(2)
                    ->schema([
                        Select::make('template_id')
                            ->label('Journal Template')
                            ->relationship('template', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),

                        TextInput::make('name')
                            ->label('Batch Name')
                            ->required()
                            ->maxLength(20),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Select::make('assigned_user_id')
                            ->label('Assigned User')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Balancing & Dimension Controls')
                    ->columns(2)
                    ->schema([
                        Select::make('balancing_account_id')
                            ->label('Balancing Account')
                            ->relationship('balancingAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->no} - {$record->name}"),

                        Select::make('reason_code')
                            ->label('Reason Code')
                            ->options(fn () => ReasonCode::query()
                                ->where('blocked', false)
                                ->orderBy('code')
                                ->pluck('description', 'code'))
                            ->searchable()
                            ->preload(),

                        Toggle::make('copy_dimensions_from_line')
                            ->label('Copy Dimensions from Line')
                            ->inline(false),
                    ]),

                Section::make('Posting Date Restrictions')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('posting_date_restriction_from')
                            ->label('Allowed From')
                            ->native(false),

                        DatePicker::make('posting_date_restriction_to')
                            ->label('Allowed To')
                            ->native(false)
                            ->afterOrEqual('posting_date_restriction_from'),
                    ]),
            ]);
    }
}
