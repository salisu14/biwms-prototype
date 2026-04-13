<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use App\Enums\ApprovalStatus;
use App\Models\Item;
use App\Models\SalesInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalesInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Header Information')
                    ->columns(3)
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?SalesInvoice $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->helperText('The code cannot be changed once the Sales invoice is created.'),

                        Select::make('customer_id')
                            ->relationship(
                                name: 'customer',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereNotNull('name')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?SalesInvoice $record) => $record?->isPosted()),

                        Select::make('status')
                            ->options(ApprovalStatus::class)
                            ->required()
                            ->native(false)
                            ->default(ApprovalStatus::DRAFT)
                            ->disabled(fn (?SalesInvoice $record) => $record?->isPosted()),

                        DatePicker::make('invoice_date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('due_date')
                            ->required(),

                        Select::make('currency_code')
                            ->options([
                                'NGN' => 'NGN - Naira',
                                'CYN' => 'CYN - Yuan',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('USD')
                            ->required(),
                    ]),

                Section::make('Invoice Lines')
                    ->schema([
                        Repeater::make('lines')
                            ->relationship('lines') // <-- important! Must match hasMany in SalesInvoice model
                            ->dehydrated()
                            ->schema([
                                Select::make('item_id')
                                    ->label('Item')
                                    ->options(Item::query()->whereNotNull('item_code')->pluck('item_code', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (! $state) {
                                            return;
                                        }

                                        $item = Item::find($state);
                                        if ($item) {
                                            $set('description', $item->description);
                                            $set('unit_price', $item->unit_price);
                                        }
                                    })
                                    ->columnSpan(2),

                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateLineTotal($set, $get)),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateLineTotal($set, $get)),

                                TextInput::make('vat_percent')
                                    ->label('VAT %')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateLineTotal($set, $get)),

                                TextInput::make('line_total')
                                    ->numeric()
                                    ->readonly()
                                    ->dehydrated()
                                    ->prefix('$'),
                            ])
                            ->columns(5)
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'New Line')
                            ->reorderableWithButtons()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateGrandTotal($set, $get)),
                    ]),

                Section::make('Summary')
                    ->columns(2)
                    ->schema([
                        TextInput::make('total_amount')
                            ->numeric()
                            ->readonly()
                            ->prefix('$')
                            ->extraInputAttributes(['class' => 'font-bold text-lg']),

                        Placeholder::make('post_info')
                            ->label('Posting Details')
                            ->hidden(fn (?SalesInvoice $record) => ! $record?->isPosted())
                            ->content(fn (SalesInvoice $record) => "Posted by {$record->posted_by} on {$record->posted_at?->format('M d, Y H:i')}"),
                    ]),
            ]);
    }

    /**
     * Calculates the total for a single line in the repeater including VAT logic.
     */
    public static function updateLineTotal(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $vatPercent = (float) ($get('vat_percent') ?? 0);

        $subtotal = $quantity * $unitPrice;
        $vatAmount = $subtotal * ($vatPercent / 100);
        $total = $subtotal + $vatAmount;

        $set('line_total', number_format($total, 2, '.', ''));

        // Push update to the grand total field outside the repeater
        self::updateGrandTotal($set, $get, true);
    }

    /**
     * Calculates the grand total of the entire invoice.
     */
    public static function updateGrandTotal(Set $set, Get $get, bool $isFromInsideRepeater = false): void
    {
        $lines = $isFromInsideRepeater ? $get('../../lines') : $get('lines');

        $total = collect($lines ?? [])
            ->map(function ($line) {
                return (float) ($line['line_total'] ?? 0);
            })
            ->sum();

        $path = $isFromInsideRepeater ? '../../total_amount' : 'total_amount';
        $set($path, number_format($total, 2, '.', ''));
    }
}
