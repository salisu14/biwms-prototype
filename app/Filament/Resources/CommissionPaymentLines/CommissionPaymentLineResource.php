<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentLines;

use App\Enums\CommissionPaymentLineStatus;
use App\Filament\Resources\CommissionPaymentLines\Pages\ListCommissionPaymentLines;
use App\Filament\Resources\CommissionPaymentLines\Pages\ViewCommissionPaymentLine;
use App\Models\CommissionPaymentLine;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionPaymentLineResource extends Resource
{
    protected static ?string $model = CommissionPaymentLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Payment Lines';

    protected static ?int $navigationSort = 85;

    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_payment_line';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('batch.batch_number')->label('Batch')->searchable(),
            TextColumn::make('settlementBatch.settlement_number')->label('Settlement')->searchable(),
            TextColumn::make('referrer.name')->searchable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('payment_amount')->money(fn (CommissionPaymentLine $record): string => $record->currency_code)->sortable(),
            TextColumn::make('remaining_amount')->money(fn (CommissionPaymentLine $record): string => $record->currency_code)->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->options(CommissionPaymentLineStatus::class),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionPaymentLines::route('/'),
            'view' => ViewCommissionPaymentLine::route('/{record}'),
        ];
    }
}
