<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubledgerOpeningBalances;

use App\Filament\Resources\SubledgerOpeningBalances\Pages\CreateSubledgerOpeningBalance;
use App\Filament\Resources\SubledgerOpeningBalances\Pages\EditSubledgerOpeningBalance;
use App\Filament\Resources\SubledgerOpeningBalances\Pages\ListSubledgerOpeningBalances;
use App\Filament\Resources\SubledgerOpeningBalances\Pages\ViewSubledgerOpeningBalance;
use App\Models\CompanyInformation;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\SubledgerOpeningBalance;
use App\Models\Vendor;
use App\Services\Business\BusinessContextService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

final class SubledgerOpeningBalanceResource extends Resource
{
    protected static ?string $model = SubledgerOpeningBalance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Scale;

    protected static string|null|\UnitEnum $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Opening Balances';

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'subledger_opening_balance';
    }

    public static function getEloquentQuery(): Builder
    {
        $activeBusinessId = app(BusinessContextService::class)->resolveId();

        return parent::getEloquentQuery()
            ->when($activeBusinessId > 0, fn (Builder $query): Builder => $query->where('business_id', $activeBusinessId));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Opening Balance For')
                ->description('Choose the party whose opening receivable or payable is being established.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('party_type')
                        ->label('Party Type')
                        ->options(['CUSTOMER' => 'Customer', 'VENDOR' => 'Vendor'])
                        ->required()
                        ->default(fn (): ?string => request()->query('party_type') ? strtoupper((string) request()->query('party_type')) : null)
                        ->disabled(fn (): bool => request()->filled('party_type'))
                        ->dehydrated()
                        ->live(),
                    Select::make('party_id')
                        ->label('Customer or Vendor')
                        ->options(function (Get $get): array {
                            $model = $get('party_type') === 'CUSTOMER' ? Customer::class : Vendor::class;
                            $name = $model === Customer::class ? 'name' : 'vendor_name';
                            $number = $model === Customer::class ? 'customer_number' : 'vendor_code';

                            return $model::query()->orderBy($name)->get([$model::make()->getKeyName(), $number, $name])->mapWithKeys(
                                fn (Customer|Vendor $party): array => [$party->getKey() => trim($party->{$number}.' — '.$party->{$name})],
                            )->all();
                        })
                        ->searchable()
                        ->required()
                        ->default(fn (): ?int => request()->integer('party_id') ?: null)
                        ->disabled(fn (): bool => request()->filled('party_id'))
                        ->dehydrated(),
                    Placeholder::make('business_context')
                        ->label('Business')
                        ->content(fn (): string => app(BusinessContextService::class)->resolveId(request()->integer('business_id') ?: null) !== null
                            ? (app(BusinessContextService::class)->resolve(request()->integer('business_id') ?: null)?->name ?? 'Active business')
                            : 'Active business'),
                    Hidden::make('business_id')
                        ->default(fn (): ?int => app(BusinessContextService::class)->resolveId(request()->integer('business_id') ?: null))
                        ->dehydrated(),
                ]),
            Section::make('Opening Balance Details')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('original_amount')
                        ->label('Opening Amount')
                        ->numeric()
                        ->minValue(0.000001)
                        ->required(),
                    Select::make('currency_code')
                        ->label('Currency')
                        ->options(fn (): array => Currency::query()->active()->orderBy('code')->pluck('code', 'code')->all())
                        ->default(fn (Get $get): string => self::partyCurrency($get('party_type'), $get('party_id')))
                        ->required()
                        ->disabled(fn (): bool => request()->filled('party_id'))
                        ->dehydrated()
                        ->live(),
                    Hidden::make('currency_id')
                        ->default(fn (Get $get): ?int => Currency::query()
                            ->where('code', strtoupper((string) $get('currency_code')))
                            ->value('id'))
                        ->dehydrated(),
                    TextInput::make('currency_factor')
                        ->label('Exchange Rate')
                        ->numeric()
                        ->default(fn (Get $get): float => self::currencyFactor(
                            (string) $get('currency_code'),
                            $get('posting_date'),
                        ))
                        ->minValue(0.000001)
                        ->required()
                        ->visible(fn (Get $get): bool => ! self::isLocalCurrency((string) $get('currency_code')))
                        ->helperText('LCY amount = opening amount × exchange rate.'),
                    Placeholder::make('amount_lcy_preview')
                        ->label('Amount (LCY)')
                        ->content(fn (Get $get): string => number_format(
                            (float) ($get('original_amount') ?? 0) * (float) ($get('currency_factor') ?: 1),
                            4,
                        )),
                    DatePicker::make('posting_date')->required()->default(now()),
                    DatePicker::make('document_date')->required()->default(now()),
                    DatePicker::make('due_date'),
                ]),
            Section::make('Reference & Description')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('external_document_number')->label('External Document No.'),
                    TextInput::make('description')->label('Opening Balance Narrative')->required()->default('Opening balance'),
                ]),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    private static function partyCurrency(?string $partyType, mixed $partyId): string
    {
        if (strtoupper((string) $partyType) === 'VENDOR' && $partyId) {
            return strtoupper((string) (Vendor::query()->find($partyId)?->currency ?: 'NGN'));
        }

        return 'NGN';
    }

    private static function isLocalCurrency(string $currencyCode): bool
    {
        return (bool) Currency::query()->where('code', strtoupper($currencyCode))->value('is_lcy')
            || strtoupper($currencyCode) === 'NGN';
    }

    private static function currencyFactor(string $currencyCode, mixed $postingDate): float
    {
        $currency = Currency::query()->where('code', strtoupper($currencyCode))->first();
        if (! $currency || $currency->isLCY()) {
            return 1.0;
        }

        return $currency->getExchangeRate($postingDate ? Carbon::parse($postingDate) : now());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('document_number')->label('Document No.'),
            TextEntry::make('party_type')->badge(),
            TextEntry::make('customer.name')->label('Customer'),
            TextEntry::make('vendor.vendor_name')->label('Vendor'),
            TextEntry::make('original_document_type')->label('Original Document Type'),
            TextEntry::make('external_document_number')->placeholder('—'),
            TextEntry::make('original_amount')
                ->label('FCY Amount')
                ->formatStateUsing(fn (mixed $state, SubledgerOpeningBalance $record): string => $record->currency_code.' '.number_format((float) ($state ?? 0), 2)),
            TextEntry::make('currency_code'),
            TextEntry::make('currency_factor')
                ->formatStateUsing(fn (mixed $state): string => number_format((float) ($state ?? 0), 4)),
            TextEntry::make('amount_lcy')
                ->label('LCY Amount')
                ->formatStateUsing(fn (mixed $state, SubledgerOpeningBalance $record): string => Number::currency((float) ($state ?? 0), self::baseCurrencyCode($record->business_id))),
            TextEntry::make('remaining_amount')
                ->label('Remaining Amount')
                ->formatStateUsing(fn (mixed $state, SubledgerOpeningBalance $record): string => $record->currency_code.' '.number_format((float) ($state ?? 0), 2)),
            TextEntry::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    SubledgerOpeningBalance::STATUS_DRAFT => 'warning',
                    SubledgerOpeningBalance::STATUS_POSTED => 'success',
                    SubledgerOpeningBalance::STATUS_REVERSED => 'danger',
                    default => 'gray',
                }),
            TextEntry::make('posting_date')->date(),
            TextEntry::make('due_date')->date()->placeholder('—'),
            TextEntry::make('controlAccount.account_number')->label('Control Account')->placeholder('—'),
            TextEntry::make('openingEquityAccount.account_number')->label('Opening Equity Account')->placeholder('—'),
        ])->columns(['default' => 1, 'md' => 2, 'xl' => 3]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')->searchable(),
                TextColumn::make('party_type')->badge(),
                TextColumn::make('customer.name')->label('Customer'),
                TextColumn::make('vendor.vendor_name')->label('Vendor'),
                TextColumn::make('original_amount')
                    ->label('Original Amount')
                    ->formatStateUsing(fn (mixed $state, SubledgerOpeningBalance $record): string => ($record->currency_code ?: self::baseCurrencyCode($record->business_id)).' '.number_format((float) ($state ?? 0), 2)),
                TextColumn::make('amount_lcy')
                    ->label('LCY Amount')
                    ->formatStateUsing(fn (mixed $state, SubledgerOpeningBalance $record): string => Number::currency((float) ($state ?? 0), self::baseCurrencyCode($record->business_id)))
                    ->toggleable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('posting_date')->date(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (SubledgerOpeningBalance $record): bool => $record->status === SubledgerOpeningBalance::STATUS_DRAFT
                        && auth()->user()?->can('update', $record) === true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubledgerOpeningBalances::route('/'),
            'create' => CreateSubledgerOpeningBalance::route('/create'),
            'view' => ViewSubledgerOpeningBalance::route('/{record}'),
            'edit' => EditSubledgerOpeningBalance::route('/{record}/edit'),
        ];
    }

    private static function baseCurrencyCode(?int $businessId): string
    {
        return (string) (CompanyInformation::query()
            ->where('business_id', $businessId ?: (app(BusinessContextService::class)->resolveId() ?? 0))
            ->value('base_currency_code') ?: config('app.base_currency', 'NGN'));
    }
}
