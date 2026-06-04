<?php

namespace App\Filament\Resources\DiscountRules;

use App\Filament\Resources\DiscountRules\Pages\CreateDiscountRule;
use App\Filament\Resources\DiscountRules\Pages\EditDiscountRule;
use App\Filament\Resources\DiscountRules\Pages\ListDiscountRules;
use App\Filament\Resources\DiscountRules\Pages\ViewDiscountRule;
use App\Filament\Resources\DiscountRules\Schemas\DiscountRuleForm;
use App\Filament\Resources\DiscountRules\Schemas\DiscountRuleInfolist;
use App\Filament\Resources\DiscountRules\Tables\DiscountRulesTable;
use App\Models\DiscountRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DiscountRuleResource extends Resource
{
    protected static ?string $model = DiscountRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return DiscountRuleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DiscountRuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscountRulesTable::configure($table);
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
            'index' => ListDiscountRules::route('/'),
            'create' => CreateDiscountRule::route('/create'),
            'view' => ViewDiscountRule::route('/{record}'),
            'edit' => EditDiscountRule::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof DiscountRule) {
            return static::getModelLabel();
        }

        $item = $record->item
            ? "{$record->item->item_code} - {$record->item->description}"
            : 'Unknown Item';
        $group = $record->customerGroup
            ? "{$record->customerGroup->code} - {$record->customerGroup->name}"
            : 'All Groups';

        return "{$item} - {$group}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'item.item_code',
            'item.description',
            'customerGroup.code',
            'customerGroup.name',
            'discount_percent',
            'start_date',
            'end_date',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var DiscountRule $record */
        return [
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'Customer Group' => $record->customerGroup
                ? "{$record->customerGroup->code} - {$record->customerGroup->name}"
                : 'All Groups',
            'Discount' => number_format((float) $record->discount_percent, 2).'%',
            'Validity' => $record->start_date?->format('d/m/Y').' - '.($record->end_date?->format('d/m/Y') ?? 'Open'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['item', 'customerGroup']);
    }
}
