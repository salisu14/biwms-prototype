<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos;

use App\Filament\Resources\PostedPurchaseCreditMemos\Pages\ListPostedPurchaseCreditMemos;
use App\Filament\Resources\PostedPurchaseCreditMemos\Pages\ViewPostedPurchaseCreditMemo;
use App\Filament\Resources\PostedPurchaseCreditMemos\Schemas\PostedPurchaseCreditMemoForm;
use App\Filament\Resources\PostedPurchaseCreditMemos\Schemas\PostedPurchaseCreditMemoInfolist;
use App\Filament\Resources\PostedPurchaseCreditMemos\Tables\PostedPurchaseCreditMemosTable;
use App\Models\PostedPurchaseCreditMemo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class PostedPurchaseCreditMemoResource extends Resource
{
    protected static ?string $model = PostedPurchaseCreditMemo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function form(Schema $schema): Schema
    {
        return PostedPurchaseCreditMemoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PostedPurchaseCreditMemoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostedPurchaseCreditMemosTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendor', 'reasonCode', 'location']);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'external_document_number',
            'vendor_invoice_number',
            'vendor_name',
            'corrects_invoice_number',
            'description',
            'reason_code',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var PostedPurchaseCreditMemo $record */
        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');

        return "{$record->document_number} - {$vendor}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PostedPurchaseCreditMemo $record */
        return [
            'Vendor' => $record->vendor_name ?: '—',
            'Corrects Invoice' => $record->corrects_invoice_number ?: '—',
            'Posted' => $record->posted ? 'Yes' : 'No',
            'Total' => Number::currency((float) $record->grand_total, $record->currency_code ?: config('app.default_currency', 'USD')),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['vendor', 'reasonCode', 'location']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostedPurchaseCreditMemos::route('/'),
            'view' => ViewPostedPurchaseCreditMemo::route('/{record}'),
        ];
    }
}
