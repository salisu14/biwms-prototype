<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionDisputes;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionDisputeType;
use App\Filament\Resources\CommissionDisputes\Pages\ListCommissionDisputes;
use App\Filament\Resources\CommissionDisputes\Pages\ViewCommissionDispute;
use App\Models\CommissionDispute;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionDisputeResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_dispute';
    }

    protected static ?string $model = CommissionDispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Disputes';

    protected static ?int $navigationSort = 81;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('dispute_number')->searchable()->sortable(),
            TextColumn::make('line.source_number')->label('Source')->searchable(),
            TextColumn::make('referrer.name')->searchable(),
            TextColumn::make('dispute_type')->badge(),
            TextColumn::make('claimed_amount')->money(fn (CommissionDispute $record): string => $record->currency_code)->sortable(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('subject')->searchable()->limit(40),
            TextColumn::make('raised_at')->dateTime()->sortable(),
            TextColumn::make('resolved_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('dispute_type')->options(CommissionDisputeType::class),
            SelectFilter::make('status')->options(CommissionDisputeStatus::class),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionDisputes::route('/'),
            'view' => ViewCommissionDispute::route('/{record}'),
        ];
    }
}
