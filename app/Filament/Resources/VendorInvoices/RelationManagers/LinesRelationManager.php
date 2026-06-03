<?php

namespace App\Filament\Resources\VendorInvoices\RelationManagers;

use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = VendorInvoiceResource::class;

    protected static ?string $title = 'Invoice Lines';

    public function form(Schema $schema): Schema
    {
        $isPosted = $this->ownerRecord->posted ?? false;

        return $schema
            ->schema([
                Section::make('Line Details')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('line_number')
                                    ->required()
                                    ->numeric()
                                    ->default(10000)
                                    ->step(10000)
                                    ->disabled($isPosted),
                                Select::make('type')
                                    ->options(['ITEM' => 'Item', 'GL_ACCOUNT' => 'G/L Account', 'FIXED_ASSET' => 'Fixed Asset', 'CHARGE' => 'Charge'])
                                    ->required()
                                    ->default('ITEM')
                                    ->live()
                                    ->native(false)
                                    ->disabled($isPosted),
                                Select::make('item_id')
                                    ->label('Item')
                                    ->relationship('item', 'description')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn ($get) => $get('type') === 'ITEM')
                                    ->required(fn ($get) => $get('type') === 'ITEM')
                                    ->disabled($isPosted),
                                Select::make('gl_account_id')
                                    ->label('G/L Account')
                                    ->relationship('glAccount', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn ($get) => $get('type') === 'GL_ACCOUNT')
                                    ->required(fn ($get) => $get('type') === 'GL_ACCOUNT')
                                    ->disabled($isPosted),
                                Select::make('asset_id')
                                    ->label('Fixed Asset')
                                    ->relationship('asset', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn ($get) => $get('type') === 'FIXED_ASSET')
                                    ->required(fn ($get) => $get('type') === 'FIXED_ASSET')
                                    ->disabled($isPosted),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('description')->required()->maxLength(255)->disabled($isPosted)->columnSpan(1),
                                TextInput::make('description_2')->maxLength(50)->disabled($isPosted),
                            ]),
                    ]),
                Section::make('Quantity & Pricing')
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextInput::make('quantity')->required()->numeric()->default(1)->step(0.0001)->live(onBlur: true)->disabled($isPosted),
                                TextInput::make('unit_of_measure_code')->label('UoM')->disabled($isPosted),
                                TextInput::make('direct_unit_cost')->required()->numeric()->prefix('₦')->step(0.0001)->live(onBlur: true)->disabled($isPosted),
                                TextInput::make('line_discount_percent')->numeric()->suffix('%')->step(0.01)->live(onBlur: true)->disabled($isPosted),
                                TextInput::make('line_amount')->required()->numeric()->prefix('₦')->step(0.01)->disabled()->dehydrated(),
                            ]),
                    ]),
                Section::make('Matching & Dimensions')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('purchase_order_id')->label('Purchase Order')->relationship('purchaseOrder', 'order_number')->searchable()->disabled($isPosted),
                                TextInput::make('purchase_order_line_no')->label('PO Line No.')->numeric()->disabled($isPosted),
                                Select::make('purchase_receipt_id')->label('Purchase Receipt')->relationship('purchaseReceipt', 'document_number')->searchable()->disabled($isPosted),
                                TextInput::make('purchase_receipt_line_no')->label('Receipt Line No.')->numeric()->disabled($isPosted),
                                Select::make('capex_project_id')->label('CapEx Project')->relationship('capExProject', 'description')->searchable()->disabled($isPosted),
                                TextInput::make('shortcut_dimension_1_code')->label('Dimension 1')->disabled($isPosted),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        $isPosted = $this->ownerRecord->posted ?? false;

        return $table
            ->columns([
                TextColumn::make('line_number')->label('Line')->sortable()->alignEnd(),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('item.item_code')->label('Item No.')->searchable()->visible(fn ($record) => $record->type === 'ITEM'),
                TextColumn::make('description')->searchable()->limit(40),
                TextColumn::make('quantity')->numeric()->alignEnd(),
                TextColumn::make('direct_unit_cost')->money('NGN')->alignEnd(),
                TextColumn::make('line_discount_percent')->label('Disc. %')->suffix('%')->alignEnd(),
                TextColumn::make('line_amount')->money('NGN')->alignEnd()->weight('bold'),
                TextColumn::make('match_status')
                    ->label('3-Way Match')
                    ->state(fn ($record) => $record->getMatchStatus())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'MATCHED' => 'success',
                        'MISMATCH' => 'danger',
                        'NO_PO', 'NO_RECEIPT' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('line_number', 'asc')
            ->headerActions([
                CreateAction::make()->visible(!$isPosted),
            ])
            ->recordActions([
                EditAction::make()->visible(!$isPosted),
                DeleteAction::make()->visible(!$isPosted),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(!$isPosted),
                ]),
            ]);
    }
}
