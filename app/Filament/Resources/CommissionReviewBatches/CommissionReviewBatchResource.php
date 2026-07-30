<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionReviewBatches;

use App\Enums\CommissionReviewBatchStatus;
use App\Filament\Resources\CommissionReviewBatches\Pages\ListCommissionReviewBatches;
use App\Filament\Resources\CommissionReviewBatches\Pages\ViewCommissionReviewBatch;
use App\Models\CommissionReviewBatch;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionReviewBatchResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_review_batch';
    }

    protected static ?string $model = CommissionReviewBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Review Batches';

    protected static ?int $navigationSort = 78;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('batch_number')->searchable()->sortable(),
            TextColumn::make('period.code')->label('Period')->sortable(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('line_count')->numeric()->sortable(),
            TextColumn::make('exception_count')->numeric()->sortable(),
            TextColumn::make('total_eligible_amount')->money(fn (CommissionReviewBatch $record): string => $record->currency_code)->sortable(),
            TextColumn::make('generated_at')->dateTime()->toggleable(),
            TextColumn::make('approved_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->options(CommissionReviewBatchStatus::class),
            SelectFilter::make('currency_code')->options(fn (): array => CommissionReviewBatch::query()->distinct()->pluck('currency_code', 'currency_code')->all()),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionReviewBatches::route('/'),
            'view' => ViewCommissionReviewBatch::route('/{record}'),
        ];
    }
}
