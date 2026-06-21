<?php

namespace App\Filament\Resources\VendorPostingGroups;

use App\Filament\Resources\VendorPostingGroups\Pages\CreateVendorPostingGroup;
use App\Filament\Resources\VendorPostingGroups\Pages\EditVendorPostingGroup;
use App\Filament\Resources\VendorPostingGroups\Pages\ListVendorPostingGroups;
use App\Filament\Resources\VendorPostingGroups\Pages\ViewVendorPostingGroup;
use App\Filament\Resources\VendorPostingGroups\Schemas\VendorPostingGroupForm;
use App\Filament\Resources\VendorPostingGroups\Schemas\VendorPostingGroupInfolist;
use App\Filament\Resources\VendorPostingGroups\Tables\VendorPostingGroupsTable;
use App\Models\VendorPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VendorPostingGroupResource extends Resource
{
    protected static ?string $model = VendorPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return VendorPostingGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorPostingGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorPostingGroupsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['payablesAccount', 'paymentDiscDebitAccount', 'paymentDiscCreditAccount', 'invoiceRoundingAccount']);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof VendorPostingGroup) {
            return static::getModelLabel();
        }

        return "{$record->code} - {$record->description}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'code',
            'description',
            'payablesAccount.name',
            'paymentDiscDebitAccount.name',
            'paymentDiscCreditAccount.name',
            'invoiceRoundingAccount.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var VendorPostingGroup $record */
        return "{$record->code} - {$record->description}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var VendorPostingGroup $record */
        return [
            'Payables Account' => $record->payablesAccount
                ? "{$record->payablesAccount->no} - {$record->payablesAccount->name}"
                : '—',
            'Discount Debit' => $record->paymentDiscDebitAccount
                ? "{$record->paymentDiscDebitAccount->no} - {$record->paymentDiscDebitAccount->name}"
                : '—',
            'Discount Credit' => $record->paymentDiscCreditAccount
                ? "{$record->paymentDiscCreditAccount->no} - {$record->paymentDiscCreditAccount->name}"
                : '—',
            'Invoice Rounding' => $record->invoiceRoundingAccount
                ? "{$record->invoiceRoundingAccount->no} - {$record->invoiceRoundingAccount->name}"
                : '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['payablesAccount', 'paymentDiscDebitAccount', 'paymentDiscCreditAccount', 'invoiceRoundingAccount']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorPostingGroups::route('/'),
            'create' => CreateVendorPostingGroup::route('/create'),
            'view' => ViewVendorPostingGroup::route('/{record}'),
            'edit' => EditVendorPostingGroup::route('/{record}/edit'),
        ];
    }
}
