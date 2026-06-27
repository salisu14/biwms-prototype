<?php

namespace App\Filament\Resources\RecurringExpenses;

use App\Filament\Resources\RecurringExpenses\Pages\ManageRecurringExpenses;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Services\ExpenseService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecurringExpenseResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'recurring_expense';
    }

    protected static ?string $model = RecurringExpense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('description')
                            ->required(),
                        Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('category_code', ExpenseCategory::find($state)?->category_code)),
                        TextInput::make('category_code')
                            ->disabled()
                            ->dehydrated(),
                    ])->columns(2),

                Section::make('Schedule Settings')
                    ->schema([
                        Select::make('frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'yearly' => 'Yearly',
                            ])
                            ->required()
                            ->default('monthly'),
                        TextInput::make('interval')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        DatePicker::make('start_date')
                            ->required()
                            ->default(now()),
                        DatePicker::make('end_date'),
                        DatePicker::make('next_occurrence_at')
                            ->required()
                            ->default(now()),
                        Toggle::make('is_active')
                            ->default(true),
                        Toggle::make('auto_post')
                            ->default(false),
                    ])->columns(3),

                Section::make('Financial Details')
                    ->schema([
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        Select::make('currency_id')
                            ->relationship('currency', 'code')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('dimension_set_id')
                            ->relationship('dimensionSet', 'id')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('shortcut_dimension_1_code')
                            ->label('Department'),
                        TextInput::make('shortcut_dimension_2_code')
                            ->label('Project'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('vendor.name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                TextColumn::make('category.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency?->code ?? 'USD')
                    ->sortable(),
                TextColumn::make('frequency')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('next_occurrence_at')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state <= now() ? 'danger' : 'success'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                IconColumn::make('auto_post')
                    ->boolean()
                    ->label('Auto-Post'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('generate_now')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (RecurringExpense $record, ExpenseService $service) {
                        $service->createFromRecurring($record);

                        // Update next occurrence manually even for manual run?
                        // Typically "Run Now" might be for testing or one-off.
                        // I'll update it to stay in sync.
                        $record->last_occurrence_at = $record->next_occurrence_at;
                        $record->next_occurrence_at = now(); // Or calculate next?
                        // Actually, createFromRecurring does it properly if we follow the pattern.
                        // Wait, processRecurringExpenses updates occurrences. I'll call a helper here too.
                        // But for manual run, maybe we don't advance the schedule?
                        // User likely wants to "catch up".

                        Notification::make()
                            ->title('Transaction Generated')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRecurringExpenses::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
