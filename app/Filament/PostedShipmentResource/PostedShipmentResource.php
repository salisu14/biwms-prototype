<?php

namespace App\Filament\PostedShipmentResource;

use App\Models\SalesShipmentHeader;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostedShipmentResource extends Resource
{
    protected static ?string $model = SalesShipmentHeader::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-truck';

    // Grouping under History as requested
    protected static string|null|\UnitEnum $navigationGroup = 'History';

    protected static ?string $navigationLabel = 'Posted Shipments (Waybill)';

    protected static ?string $modelLabel = 'Posted Sales Shipment';

    protected static ?string $slug = 'history/posted-shipments';

    /**
     * Disable creation, editing, and deletion for posted documents
     */
//    public static function canCreate(): bool => false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General')
                    ->schema([
                        TextInput::make('document_no')
                            ->label('No.')
                            ->readOnly(),
                        TextInput::make('sell_to_customer_name')
                            ->label('Customer Name')
                            ->readOnly(),
                        DatePicker::make('posting_date')
                            ->readOnly(),
                        TextInput::make('order_no')
                            ->label('Order No.')
                            ->readOnly(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_no')
                    ->label('No.')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sell_to_customer_no')
                    ->label('Customer No.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sell_to_customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_no')
                    ->label('Source Order')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('correction')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('location_code')
                    ->label('Location')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('posting_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('posting_date', '>=', $data['from']))
                            ->when($data['to'], fn ($q) => $q->whereDate('posting_date', '<=', $data['to']));
                    }),
                Tables\Filters\SelectFilter::make('location_code')
                    ->label('Location'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('print_waybill')
                    ->label('Print Waybill')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(fn ($record) => /* Logic for PDF generation */ null),
            ])
            ->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Group::make([
                            Infolists\Components\TextEntry::make('document_no')->label('No.')->weight('bold'),
                            Infolists\Components\TextEntry::make('sell_to_customer_no')->label('Customer No.'),
                            Infolists\Components\TextEntry::make('sell_to_customer_name')->label('Customer Name'),
                        ]),
                        Group::make([
                            Infolists\Components\TextEntry::make('posting_date')->date(),
                            Infolists\Components\TextEntry::make('document_date')->date(),
                            Infolists\Components\TextEntry::make('order_no')->label('Order No.'),
                        ]),
                        Group::make([
                            Infolists\Components\TextEntry::make('shipment_method_code')->label('Shipment Method'),
                            Infolists\Components\TextEntry::make('shipping_agent_code')->label('Shipping Agent'),
                            Infolists\Components\TextEntry::make('package_tracking_no')->label('Tracking No.'),
                        ]),
                    ]),

                Section::make('Lines')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('no')->label('Item No.'),
                                Infolists\Components\TextEntry::make('description'),
                                Infolists\Components\TextEntry::make('quantity')->numeric(),
                                Infolists\Components\TextEntry::make('unit_of_measure_code')->label('UOM'),
                                Infolists\Components\TextEntry::make('location_code')->label('Loc'),
                            ])->columns(5)
                    ])
            ]);
    }

    public static function getPages(): array
    {
//        return [
//            'index' => ListPostedShipments::route('/'),
//            'view' => ViewPostedShipment::route('/{record}'),
//        ];
    }
}
