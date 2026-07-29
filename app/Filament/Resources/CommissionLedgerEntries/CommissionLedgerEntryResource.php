<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionLedgerEntries;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Filament\Resources\CommissionLedgerEntries\Pages\ListCommissionLedgerEntries;
use App\Filament\Resources\CommissionLedgerEntries\Pages\ViewCommissionLedgerEntry;
use App\Models\CommissionLedgerEntry;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionLedgerEntryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_ledger';
    }

    protected static ?string $model = CommissionLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Ledger';

    protected static ?int $navigationSort = 76;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_number')->sortable()->searchable(),
                TextColumn::make('posting_date')->date()->sortable(),
                TextColumn::make('entry_type')->badge()->sortable(),
                TextColumn::make('referrer.name')->searchable()->toggleable(),
                TextColumn::make('source_number')->label('Source')->searchable()->sortable(),
                TextColumn::make('currency_code')->badge(),
                TextColumn::make('amount')->money(fn (CommissionLedgerEntry $record): string => $record->currency_code)->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('reverses_entry_id')->label('Reverses')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')->label('Created By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entry_type')->options(CommissionLedgerEntryType::class),
                SelectFilter::make('status')->options(CommissionLedgerEntryStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionLedgerEntries::route('/'),
            'view' => ViewCommissionLedgerEntry::route('/{record}'),
        ];
    }
}
