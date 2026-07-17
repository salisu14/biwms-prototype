<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardHistories;

use App\Filament\Resources\EmployeeIdCardHistories\Pages\ListEmployeeIdCardHistories;
use App\Models\EmployeeIdCardHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeIdCardHistoryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_id_card_history';
    }

    protected static ?string $model = EmployeeIdCardHistory::class;

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Identity';

    protected static ?string $navigationLabel = 'Card History';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('event')->badge()->searchable(),
                TextColumn::make('card.card_number')->label('Card No.')->searchable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('actor.name')->label('Actor')->toggleable(),
                TextColumn::make('description')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'issued' => 'Issued',
                        'previewed' => 'Previewed',
                        'downloaded' => 'Downloaded',
                        'printed' => 'Printed',
                        'verified' => 'Verified',
                        'regenerated' => 'Regenerated',
                        'lost' => 'Lost',
                        'revoked' => 'Revoked',
                        'replaced' => 'Replaced',
                        'expired' => 'Expired',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeIdCardHistories::route('/'),
        ];
    }
}
