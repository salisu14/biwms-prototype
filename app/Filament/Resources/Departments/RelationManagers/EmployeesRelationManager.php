<?php

namespace App\Filament\Resources\Departments\RelationManagers;

use App\Filament\Resources\Departments\DepartmentResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeAssignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Employees';
    }

    protected static ?string $relatedResource = DepartmentResource::class;

    protected static ?string $recordTitleAttribute = 'Employee';

    protected static ?string $title = 'Employees';
    protected static ?string $modelLabel = 'Employee Assignment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('employee_id')
                    ->relationship('employee', 'id') // Change to 'name' if available
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('position_title')
                    ->required(),

                Select::make('assignment_type')
                    ->options([
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                        'temporary' => 'Temporary',
                    ])
                    ->required(),

                TextInput::make('allocation_percentage')
                    ->numeric()
                    ->default(100)
                    ->suffix('%')
                    ->required(),

                DatePicker::make('assignment_date')
                    ->default(now())
                    ->required(),

                DatePicker::make('end_date'),

                Toggle::make('is_default_dimension')
                    ->label('Use as Default Dimension')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('position_title')
            ->columns([
                TextColumn::make('employee.id')
                    ->label('Employee')
                    ->sortable(),

                TextColumn::make('position_title')
                    ->searchable(),

                TextColumn::make('assignment_type')
                    ->badge(),

                TextColumn::make('allocation_percentage')
                    ->suffix('%'),

                TextColumn::make('assignment_date')
                    ->date(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Only')
                    ->queries(
                        true: fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()),
                        false: fn ($query) => $query->whereNotNull('end_date')->where('end_date', '<', now()),
                    ),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
