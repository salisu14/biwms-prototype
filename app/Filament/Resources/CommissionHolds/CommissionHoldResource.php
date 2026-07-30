<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionHolds;

use App\Enums\CommissionHoldStatus;
use App\Enums\CommissionHoldType;
use App\Filament\Resources\CommissionHolds\Pages\ListCommissionHolds;
use App\Filament\Resources\CommissionHolds\Pages\ViewCommissionHold;
use App\Models\CommissionHold;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionHoldResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_hold';
    }

    protected static ?string $model = CommissionHold::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPauseCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Holds';

    protected static ?int $navigationSort = 80;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('referrer.name')->searchable(),
            TextColumn::make('line.source_number')->label('Source')->searchable(),
            TextColumn::make('amount')->money(fn (CommissionHold $record): string => $record->currency_code)->sortable(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('hold_type')->badge(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('reason')->limit(40)->toggleable(),
            TextColumn::make('placed_at')->dateTime()->sortable(),
            TextColumn::make('released_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('hold_type')->options(CommissionHoldType::class),
            SelectFilter::make('status')->options(CommissionHoldStatus::class),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionHolds::route('/'),
            'view' => ViewCommissionHold::route('/{record}'),
        ];
    }
}
