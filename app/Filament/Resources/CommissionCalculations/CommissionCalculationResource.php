<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionCalculations;

use App\Enums\CommissionCalculationStatus;
use App\Filament\Resources\CommissionCalculations\Pages\ListCommissionCalculations;
use App\Filament\Resources\CommissionCalculations\Pages\ViewCommissionCalculation;
use App\Models\CommissionCalculation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionCalculationResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_calculation';
    }

    protected static ?string $model = CommissionCalculation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Calculations';

    protected static ?int $navigationSort = 75;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_number')->label('Source')->searchable()->sortable(),
                TextColumn::make('source_posting_date')->date()->sortable(),
                TextColumn::make('customer.name')->searchable()->toggleable(),
                TextColumn::make('referrer.name')->searchable()->toggleable(),
                TextColumn::make('plan.name')->label('Plan')->toggleable(),
                TextColumn::make('currency_code')->badge(),
                TextColumn::make('calculated_base_amount')->money(fn (CommissionCalculation $record): string => $record->currency_code)->sortable(),
                TextColumn::make('calculated_commission_amount')->money(fn (CommissionCalculation $record): string => $record->currency_code)->sortable(),
                TextColumn::make('calculation_status')->badge()->sortable(),
                TextColumn::make('calculated_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('calculation_status')->options(CommissionCalculationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionCalculations::route('/'),
            'view' => ViewCommissionCalculation::route('/{record}'),
        ];
    }
}
