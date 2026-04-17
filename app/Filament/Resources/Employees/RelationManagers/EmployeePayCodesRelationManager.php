<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\Employees\EmployeeResource;
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

    protected static ?string $relatedResource = EmployeeResource::class;

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(2)->schema([
                    Select::make('pay_code_id')
                        ->label('Pay Code')
                        ->relationship('payCode', 'name')
                        ->getOptionLabelFromRecordUsing(fn (PayCode $record) => "{$record->code} - {$record->name}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (!$state) return;
                            $payCode = PayCode::find($state);
                            if ($payCode) {
                                // Automatically pull defaults from the master Pay Code
                                $set('amount', $payCode->default_amount);
                                $set('percentage', $payCode->default_percentage);
                            }
                        }),

                    TextInput::make('amount')
                        ->numeric()
                        ->label('Fixed Amount')
                        ->prefix('$')
                        ->placeholder('0.00'),

                    TextInput::make('percentage')
                        ->numeric()
                        ->label('Percentage')
                        ->suffix('%')
                        ->placeholder('0.00'),
                ]),

                Grid::make(2)->schema([
                    DatePicker::make('effective_date')
                        ->label('Starts On')
                        ->default(now())
                        ->required(),

                    DatePicker::make('end_date')
                        ->label('Ends On')
                        ->helperText('Leave empty for ongoing pay components'),
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
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('payCode.name')
                    ->label('Pay Component')
                    ->description(fn ($record) => $record->payCode->type->getLabel())
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money()
                    ->sortable(),

                TextColumn::make('percentage')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->state(function ($record) {
                        $now = now();
                        if ($record->end_date && $now->greaterThan($record->end_date)) return 'Expired';
                        if ($now->lessThan($record->effective_date)) return 'Scheduled';
                        return 'Active';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Active' => 'success',
                        'Scheduled' => 'info',
                        'Expired' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Only Active')
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
