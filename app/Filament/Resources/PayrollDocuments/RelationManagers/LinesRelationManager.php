<?php

namespace App\Filament\Resources\PayrollDocuments\RelationManagers;

use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Models\Employee;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = PayrollDocumentResource::class;

    protected static ?string $recordTitleAttribute = 'Payroll Line';

    protected static ?string $title = 'Payroll Lines';
    protected static ?string $pluralTitle = 'Payroll Lines';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('employee_id')
                    ->relationship('employee', 'employee_number')
                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('pay_code_id')
                    ->relationship('payCode', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->prefix('$'),

                TextInput::make('description')
                    ->maxLength(255)
                    ->placeholder('Optional line description'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.employee_number')
                    ->label('Emp. No')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->state(fn ($record) => "{$record->employee->first_name} {$record->employee->last_name}")
                    ->searchable(['first_name', 'last_name']),

                TextColumn::make('payCode.code')
                    ->label('Pay Code')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('amount')
                    ->money()
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total')),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
