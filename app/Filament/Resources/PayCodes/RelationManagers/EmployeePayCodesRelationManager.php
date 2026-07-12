<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayCodes\RelationManagers;

use App\Enums\CalculationMethod;
use App\Filament\Resources\PayCodes\PayCodeResource;
use App\Models\Employee;
use App\Models\PayCode;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeePayCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'employeePayCodes';

    protected static ?string $relatedResource = PayCodeResource::class;

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(2)->schema([
                    Select::make('employee_id')
                        ->label('Employee')
                        ->relationship('employee', 'employee_number')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                        ->searchable()
                        ->preload()
                        // Only required if we are NOT on the Employee resource
                        ->required(fn () => ! ($this->getOwnerRecord() instanceof Employee))
                        // Hide this field if we are already inside the Employee edit page
                        ->hidden(fn () => $this->getOwnerRecord() instanceof Employee)
                        // Prevent sending a null value that might overwrite the automatic association
                        ->dehydrated(fn ($state) => filled($state)),

                    Select::make('pay_code_id')
                        ->label('Pay Code')
                        ->relationship('payCode', 'name')
                        ->getOptionLabelFromRecordUsing(fn (PayCode $record) => "{$record->code} - {$record->name}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }
                            $payCode = PayCode::find($state);
                            if ($payCode) {
                                $set('amount', $payCode->default_amount);
                                $set('percentage', $payCode->default_percentage);
                            }
                        }),

                    TextInput::make('amount')
                        ->numeric()
                        ->label('Fixed Amount')
                        ->prefix('$')
                        ->helperText('Used for Fixed Amount calculation'),

                    TextInput::make('percentage')
                        ->numeric()
                        ->label('Percentage')
                        ->suffix('%')
                        ->helperText('Used for % of Base Salary calculation'),
                ]),

                Grid::make(2)->schema([
                    DatePicker::make('effective_date')
                        ->label('Starts On')
                        ->default(now())
                        ->required(),

                    DatePicker::make('end_date')
                        ->label('Ends On')
                        ->helperText('Leave empty for ongoing pay codes'),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payCode.code')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('payCode.name')
                    ->label('Pay Component')
                    ->description(fn ($record) => $record?->payCode?->type?->getLabel())
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money()
                    ->visible(fn ($record) => $record?->payCode?->calculation_method === CalculationMethod::FIXED_AMOUNT),

                TextColumn::make('percentage')
                    ->label('Rate')
                    ->suffix('%')
                    ->visible(fn ($record) => $record?->payCode?->calculation_method === CalculationMethod::PERCENTAGE),

                TextColumn::make('effective_date')
                    ->label('Validity')
                    ->state(function ($record) {
                        if (! $record || ! $record->effective_date) {
                            return null;
                        }

                        return $record->effective_date->format('M d, Y').($record->end_date ? ' to '.$record->end_date->format('M d, Y') : ' (Ongoing)');
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->state(function ($record) {
                        if (! $record || ! $record->effective_date) {
                            return null;
                        }

                        return now()->between($record->effective_date, $record->end_date ?? now()->addYear()) ? 'Active' : 'Inactive';
                    })
                    ->color(fn ($state) => $state === 'Active' ? 'success' : 'gray'),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Show Only Active')
                    ->query(fn (Builder $query) => $query->where('effective_date', '<=', now())
                        ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Assign Pay Code'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
