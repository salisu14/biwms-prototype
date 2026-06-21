<?php

namespace App\Filament\Resources\ActualOverheadCosts\Schemas;

use App\Models\ChartOfAccount;
use App\Models\OverheadCostCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActualOverheadCostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Source & Identification')
                    ->description('Identify where this overhead cost originated.')
                    ->columns(2)
                    ->schema([
                        Select::make('work_center_id')
                            ->label('Work Center')
                            ->relationship('workCenter', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('machine_center_id')
                            ->label('Machine Center')
                            ->relationship('machineCenter', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Work Center Level Overhead'),

                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Period & Classification')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('period')
                            ->label('Fiscal Period')
                            ->required()
                            ->native(false)
                            ->displayFormat('M Y'),

                        TextInput::make('fiscal_year')
                            ->label('Fiscal Year')
                            ->required()
                            ->numeric()
                            ->length(4),

                        TextInput::make('period_no')
                            ->label('Period No.')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12),

                        Select::make('cost_type_code')
                            ->label('Cost Category')
                            ->options(fn () => OverheadCostCategory::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (OverheadCostCategory $category): array => [
                                    $category->code => "{$category->name} ({$category->code})",
                                ])
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, $set): void {
                                if (! $state) {
                                    $set('cost_type', null);

                                    return;
                                }

                                $category = OverheadCostCategory::query()->where('code', $state)->first();
                                $set('cost_type', $category?->name);
                            }),

                        TextInput::make('cost_type')
                            ->label('Category Name')
                            ->readOnly()
                            ->required()
                            ->dehydrated(),
                    ]),

                Section::make('Financial Details')
                    ->description('Manage amount values and G/L mapping.')
                    ->schema([
                        // Re-structured into a 2-column grid with stacked inputs for better visibility
                        Grid::make(2)->schema([
                            Grid::make(1)->schema([
                                TextInput::make('amount')
                                    ->label('Actual Amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₦')
                                    ->live(onBlur: true)
                                    ->extraInputAttributes(['class' => 'text-lg font-bold']),

                                TextInput::make('allocated_amount')
                                    ->label('Allocated Amount')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('₦')
                                    ->live(onBlur: true),

                                TextInput::make('remaining_display')
                                    ->label('Remaining to Allocate')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($state, $get) => '₦ '.number_format(
                                        (float) ($get('amount') ?? 0) - (float) ($get('allocated_amount') ?? 0),
                                        4
                                    )),
                            ])->columnSpan(1),

                            Grid::make(1)->schema([
                                Select::make('gl_account_id')
                                    ->label('G/L Account Mapping')
                                    ->relationship('glAccount', 'account_number')
                                    ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set) => $set('gl_account_no', ChartOfAccount::find($state)?->account_number)),

                                TextInput::make('gl_account_no')
                                    ->label('G/L Account No. (Ref)')
                                    ->required()
                                    ->readOnly()
                                    ->placeholder('System will sync from selection'),
                            ])->columnSpan(1),
                        ]),
                    ]),

                Section::make('Document Reference')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('document_type')
                                ->placeholder('e.g. VOUCHER'),
                            TextInput::make('document_no')
                                ->placeholder('e.g. JV-2024-001'),
                            DatePicker::make('document_date')
                                ->native(false),
                        ]),
                        Textarea::make('description')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Posting & Audit')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'unallocated' => 'Unallocated',
                                'partial' => 'Partially Allocated',
                                'fully_allocated' => 'Fully Allocated',
                                'variance_posted' => 'Variance Posted',
                            ])
                            ->required()
                            ->default('unallocated')
                            ->native(false),

                        Select::make('variance_journal_batch_id')
                            ->label('Variance Journal Batch')
                            ->relationship('varianceJournalBatch', 'name')
                            ->placeholder('Not yet posted to variance'),

                        DateTimePicker::make('variance_posted_at')
                            ->label('Variance Posted At')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
