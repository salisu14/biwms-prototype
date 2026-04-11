<?php

namespace App\Filament\Resources\ExpenseCategories\RelationManagers;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Models\ExpenseBudget;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BudgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'budgets';

    protected static ?string $relatedResource = ExpenseCategoryResource::class;

    protected static ?string $title = 'Budget Planning';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Budget Identity')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('budget_name')
                                ->placeholder('e.g., FY'.now()->year.' Operating Budget')
                                ->required(),
                            TextInput::make('fiscal_year')
                                ->numeric()
                                ->required()
                                ->default(now()->year),
                        ]),
                    ]),

                Section::make('Monthly Targets')
                    ->description('Set budget values for each month. The annual total will update automatically.')
                    ->schema([
                        Grid::make(4)->schema([
                            $this->getMonthField('january'),
                            $this->getMonthField('february'),
                            $this->getMonthField('march'),
                            $this->getMonthField('april'),
                            $this->getMonthField('may'),
                            $this->getMonthField('june'),
                            $this->getMonthField('july'),
                            $this->getMonthField('august'),
                            $this->getMonthField('september'),
                            $this->getMonthField('october'),
                            $this->getMonthField('november'),
                            $this->getMonthField('december'),
                        ]),
                    ]),

                Section::make('Calculated Totals')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('annual_total')
                                ->label('Annual Total (Calculated)')
                                ->numeric()
                                ->prefix('$')
                                ->disabled()
                                ->dehydrated()
                                ->helperText('Sum of all monthly targets above.'),
                            Toggle::make('is_active')
                                ->label('Active Budget')
                                ->default(true)
                                ->helperText('Only active budgets are used for variance reporting.'),
                        ]),
                    ]),
            ]);
    }

    /**
     * Helper to create a monthly field with reactive calculation logic
     */
    protected function getMonthField(string $month): TextInput
    {
        return TextInput::make($month)
            ->label(ucfirst($month))
            ->numeric()
            ->default(0)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Set $set, Get $get) {
                $total = collect([
                    'january', 'february', 'march', 'april', 'may', 'june',
                    'july', 'august', 'september', 'october', 'november', 'december',
                ])->sum(fn ($m) => (float) $get($m));

                $set('annual_total', $total);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('budget_name')
            ->columns([
                TextColumn::make('fiscal_year')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('budget_name')
                    ->searchable()
                    ->description(fn ($record) => $record->is_active ? 'Currently Active' : 'Draft/Inactive'),
                TextColumn::make('annual_total')
                    ->label('Total Budget')
                    ->money()
                    ->sortable()
                    ->alignment('right'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('fiscal_year', 'desc')
            ->filters([
                SelectFilter::make('fiscal_year')
                    ->options(fn () => ExpenseBudget::distinct()->pluck('fiscal_year', 'fiscal_year')->toArray()),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-m-plus-circle')
                    ->mutateDataUsing(function (array $data): array {
                        $data['category_code'] = $this->getOwnerRecord()->category_code;
                        $data['account_type'] = $this->getOwnerRecord()->account_type;

                        // Final safety check for the sum
                        $data['annual_total'] = collect([
                            'january', 'february', 'march', 'april', 'may', 'june',
                            'july', 'august', 'september', 'october', 'november', 'december',
                        ])->sum(fn ($m) => (float) ($data[$m] ?? 0));

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
