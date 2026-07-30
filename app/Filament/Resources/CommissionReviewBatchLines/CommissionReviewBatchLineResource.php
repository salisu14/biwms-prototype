<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionReviewBatchLines;

use App\Enums\CommissionReviewLineStatus;
use App\Filament\Resources\CommissionReviewBatchLines\Pages\ListCommissionReviewBatchLines;
use App\Filament\Resources\CommissionReviewBatchLines\Pages\ViewCommissionReviewBatchLine;
use App\Models\CommissionReviewBatchLine;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionReviewBatchLineResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_review_batch';
    }

    protected static ?string $model = CommissionReviewBatchLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Review Lines';

    protected static ?int $navigationSort = 79;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('batch.batch_number')->label('Batch')->searchable(),
            TextColumn::make('referrer.name')->searchable(),
            TextColumn::make('source_number')->searchable(),
            TextColumn::make('source_posting_date')->date()->sortable(),
            TextColumn::make('entry_type')->badge(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('eligible_amount')->money(fn (CommissionReviewBatchLine $record): string => $record->currency_code)->sortable(),
            TextColumn::make('held_amount')->money(fn (CommissionReviewBatchLine $record): string => $record->currency_code)->toggleable(),
            TextColumn::make('approved_amount')->money(fn (CommissionReviewBatchLine $record): string => $record->currency_code)->sortable(),
            TextColumn::make('review_status')->badge()->sortable(),
            TextColumn::make('exception_code')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('review_status')->options(CommissionReviewLineStatus::class),
            SelectFilter::make('currency_code')->options(fn (): array => CommissionReviewBatchLine::query()->distinct()->pluck('currency_code', 'currency_code')->all()),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionReviewBatchLines::route('/'),
            'view' => ViewCommissionReviewBatchLine::route('/{record}'),
        ];
    }
}
