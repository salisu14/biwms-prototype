<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionLiabilityPostings;

use App\Enums\CommissionLiabilityPostingStatus;
use App\Filament\Resources\CommissionLiabilityPostings\Pages\ListCommissionLiabilityPostings;
use App\Filament\Resources\CommissionLiabilityPostings\Pages\ViewCommissionLiabilityPosting;
use App\Models\CommissionLiabilityPosting;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionLiabilityPostingResource extends Resource
{
    protected static ?string $model = CommissionLiabilityPosting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Liabilities';

    protected static ?int $navigationSort = 84;

    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_liability';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('document_number')->searchable()->sortable(),
            TextColumn::make('settlementBatch.settlement_number')->label('Settlement')->searchable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('net_liability_amount')->money(fn (CommissionLiabilityPosting $record): string => $record->currency_code)->sortable(),
            TextColumn::make('posting_date')->date()->sortable(),
            TextColumn::make('posted_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->options(CommissionLiabilityPostingStatus::class),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionLiabilityPostings::route('/'),
            'view' => ViewCommissionLiabilityPosting::route('/{record}'),
        ];
    }
}
