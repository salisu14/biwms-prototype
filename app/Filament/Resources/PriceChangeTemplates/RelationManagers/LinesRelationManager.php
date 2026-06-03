<?php

namespace App\Filament\Resources\PriceChangeTemplates\RelationManagers;

use App\Filament\Resources\PriceChangeTemplates\PriceChangeTemplateResource;
use App\Models\Business;
use App\Models\CustomerGroup;
use App\Models\Item;
use App\Services\Inventory\ItemService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = PriceChangeTemplateResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Line Target')
                    ->description('Define if this rule applies to a specific item or an entire category.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('item_id')
                                    ->label('Specific Item')
                                    ->relationship(
                                        'item',
                                        'description',
                                        fn ($query) => $query->where('item_type', 'FINISHED_GOOD')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->disabled(fn (Get $get): bool => filled($get('category_id')))
                                    ->helperText('Leave empty to apply to a whole category instead.'),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'category_name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->disabled(fn (Get $get): bool => filled($get('item_id')))
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_path)
                                    ->helperText('Select a category to apply this change to all items within it.'),
                            ]),
                    ]),

                Section::make('Scope Extensions')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('business_id')
                            ->label('Business Unit')
                            ->options(fn (): array => Business::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->default(fn (): ?int => session('active_business_id'))
                            ->getOptionLabelUsing(fn ($value): ?string => filled($value)
                                ? Business::query()->whereKey($value)->value('name')
                                : null)
                            ->searchable()
                            ->preload()
                            ->placeholder('All Businesses'),

                        Select::make('customer_group_id')
                            ->label('Customer Group')
                            ->options(fn (): array => CustomerGroup::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => filled($value)
                                ? CustomerGroup::query()->whereKey($value)->value('name')
                                : null)
                            ->searchable()
                            ->preload()
                            ->placeholder('All Customer Groups'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn ($record) => $record->item?->description ?? $record->category?->category_name)
            ->columns([
                IconColumn::make('type')
                    ->label('')
                    ->icon(fn ($record) => $record->item_id ? 'heroicon-m-shopping-cart' : 'heroicon-m-tag')
                    ->color(fn ($record) => $record->item_id ? 'primary' : 'success')
                    ->tooltip(fn ($record) => $record->item_id ? 'Specific Item' : 'Category Rule'),

                TextColumn::make('target')
                    ->label('Target Name')
                    ->state(function ($record) {
                        if ($record->item) {
                            return $record->item->description;
                        }

                        return $record->category?->category_name ?? 'Undefined';
                    })
                    ->description(fn ($record) => $record->item ? "Item: {$record->item->item_code}" : "Category Path: {$record->category?->full_path}")
                    ->searchable(['item.description', 'item.item_code', 'category.category_name']),

                TextColumn::make('preview_new_unit_price')
                    ->label('Preview New Price')
                    ->state(function ($record): string|float|null {
                        if ($record->applied_at) {
                            return (float) ($record->new_unit_price ?? 0);
                        }

                        if ($record->item_id) {
                            return app(ItemService::class)->previewNewUnitPrice($this->getOwnerRecord(), $record);
                        }

                        return null;
                    })
                    ->formatStateUsing(function ($state, $record): string {
                        if (! $record->item_id) {
                            return 'Varies by item';
                        }

                        return $state === null ? '—' : '₦'.number_format((float) $state, 2);
                    })
                    ->description(function (): string {
                        $template = $this->getOwnerRecord();

                        return match ($template->base) {
                            'cost' => 'Based on Cost',
                            'price' => 'Based on Current Retail Price',
                            default => 'Based on Template Setting',
                        };
                    })
                    ->badge()
                    ->color(fn ($record): string => $record->applied_at ? 'success' : 'warning'),

                TextColumn::make('estimated_affected_items')
                    ->label('Affected Items')
                    ->state(function ($record): int|string {
                        if ($record->item_id) {
                            return 1;
                        }

                        if ($record->category_id) {
                            return Item::query()
                                ->where('item_category_id', $record->category_id)
                                ->where('item_type', 'FINISHED_GOOD')
                                ->count();
                        }

                        return '—';
                    })
                    ->badge()
                    ->color('info')
                    ->tooltip(fn ($record): string => $record->item_id
                        ? 'Single finished good'
                        : 'Estimated finished goods in the selected category'),

                TextColumn::make('category.category_name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->since()
                    ->placeholder('Pending')
                    ->toggleable(),

                TextColumn::make('business.name')
                    ->label('Business')
                    ->placeholder('Global')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customerGroup.name')
                    ->label('Cust. Group')
                    ->placeholder('All Groups')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_item_rule')
                    ->label('Rule Type')
                    ->placeholder('All Rules')
                    ->trueLabel('Items Only')
                    ->falseLabel('Categories Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('item_id'),
                        false: fn (Builder $query) => $query->whereNotNull('category_id'),
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalHeading('Add Price Change Rule')
                    ->icon('heroicon-m-plus'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No rules defined')
            ->emptyStateDescription('Start by adding items or categories to this price change template.')
            ->emptyStateIcon('heroicon-o-list-bullet');
    }
}
