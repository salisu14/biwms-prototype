<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardVerificationLogs;

use App\Filament\Resources\EmployeeIdCardVerificationLogs\Pages\ListEmployeeIdCardVerificationLogs;
use App\Models\EmployeeIdCardVerificationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeIdCardVerificationLogResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_id_card_verification_log';
    }

    protected static ?string $model = EmployeeIdCardVerificationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Identity';

    protected static ?string $navigationLabel = 'Verification Logs';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('verified_at', 'desc')
            ->columns([
                TextColumn::make('verified_at')->dateTime()->sortable(),
                TextColumn::make('result')->badge()->searchable(),
                TextColumn::make('card.card_number')->label('Card No.')->searchable(),
                TextColumn::make('card.employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('ip_address')->toggleable(),
                TextColumn::make('device')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location_code')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->options([
                        'active' => 'Active',
                        'invalid' => 'Invalid',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeIdCardVerificationLogs::route('/'),
        ];
    }
}
