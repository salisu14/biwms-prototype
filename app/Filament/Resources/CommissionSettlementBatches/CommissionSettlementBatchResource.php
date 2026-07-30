<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionSettlementBatches;

use App\Enums\CommissionSettlementBatchStatus;
use App\Filament\Resources\CommissionSettlementBatches\Pages\ListCommissionSettlementBatches;
use App\Filament\Resources\CommissionSettlementBatches\Pages\ViewCommissionSettlementBatch;
use App\Models\CommissionSettlementBatch;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionSettlementBatchResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_settlement_batch';
    }

    protected static ?string $model = CommissionSettlementBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Settlement Prep';

    protected static ?int $navigationSort = 82;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('settlement_number')->searchable()->sortable(),
            TextColumn::make('reviewBatch.batch_number')->label('Review Batch')->searchable(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('referrer_count')->numeric(),
            TextColumn::make('line_count')->numeric(),
            TextColumn::make('total_net_amount')->money(fn (CommissionSettlementBatch $record): string => $record->currency_code)->sortable(),
            TextColumn::make('prepared_at')->dateTime()->sortable(),
            TextColumn::make('approved_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('locked_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->options(CommissionSettlementBatchStatus::class),
            SelectFilter::make('currency_code')->options(fn (): array => CommissionSettlementBatch::query()->distinct()->pluck('currency_code', 'currency_code')->all()),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionSettlementBatches::route('/'),
            'view' => ViewCommissionSettlementBatch::route('/{record}'),
        ];
    }
}
