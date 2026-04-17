<?php

namespace App\Filament\Resources\EmployeePostingGroups\RelationManagers;

use App\Filament\Resources\EmployeePostingGroups\EmployeePostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $relatedResource = EmployeePostingGroupResource::class;

    protected static ?string $recordTitleAttribute = 'employee_number';

    public function form(Schema $schema): Schema
    {
        // Typically handled in EmployeeResource, but can add quick-edit here if needed
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_number')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('Employee Name')
                    ->state(fn($record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name']),

                TextColumn::make('job_title')
                    ->label('Position')
                    ->searchable(),

                TextColumn::make('assignment_type')
                    ->label('Assignment')
                    ->badge(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Use associate/dissociate if applicable
            ])
            ->recordActions([
                //
            ]);
    }
}
