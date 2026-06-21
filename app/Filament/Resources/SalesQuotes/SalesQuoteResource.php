<?php

namespace App\Filament\Resources\SalesQuotes;

use App\Filament\Resources\SalesQuotes\Pages\CreateSalesQuote;
use App\Filament\Resources\SalesQuotes\Pages\EditSalesQuote;
use App\Filament\Resources\SalesQuotes\Pages\ListSalesQuotes;
use App\Filament\Resources\SalesQuotes\Pages\ViewSalesQuote;
use App\Filament\Resources\SalesQuotes\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\SalesQuotes\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\SalesQuotes\Schemas\SalesQuoteForm;
use App\Filament\Resources\SalesQuotes\Schemas\SalesQuoteInfolist;
use App\Filament\Resources\SalesQuotes\Tables\SalesQuotesTable;
use App\Models\SalesQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesQuoteResource extends Resource
{
    protected static ?string $model = SalesQuote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SalesQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesQuotesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'items' => ItemsRelationManager::class,
            RevisionsRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof SalesQuote) {
            return static::getModelLabel();
        }

        $customer = $record->customer?->name ?? 'Unknown Customer';

        return "{$record->quote_no} - {$customer}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'quote_no',
            'customer.name',
            'customer.customer_number',
            'status',
            'approval_status',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var SalesQuote $record */
        $customer = $record->customer?->name ?? 'Unknown Customer';

        return "{$record->quote_no} - {$customer}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var SalesQuote $record */
        return [
            'Customer' => $record->customer?->customer_number
                ? "{$record->customer->customer_number} - ".($record->customer?->name ?? '—')
                : ($record->customer?->name ?? '—'),
            'Status' => $record->status?->label() ?? (string) $record->status,
            'Approval' => $record->approval_status?->label() ?? (string) $record->approval_status,
            'Total' => number_format((float) $record->total_amount, 2).' '.($record->currency_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesQuotes::route('/'),
            'create' => CreateSalesQuote::route('/create'),
            'view' => ViewSalesQuote::route('/{record}'),
            'edit' => EditSalesQuote::route('/{record}/edit'),
        ];
    }
}
