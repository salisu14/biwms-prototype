<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PerformanceRatingScaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scale Identification')
                    ->icon('heroicon-o-scale')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Scale Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g. ANNUAL_5PT')
                                    ->prefixIcon('heroicon-m-hashtag')
                                    ->dehydrateStateUsing(
                                        fn (?string $state): ?string =>
                                        filled($state)
                                            ? Str::upper(
                                            Str::slug($state, '_')
                                        )
                                            : null
                                    ),

                                TextInput::make('name')
                                    ->label('Scale Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(
                                        'e.g. Annual Review 5-Point Scale'
                                    )
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        function (
                                            Set $set,
                                            Get $get,
                                            ?string $state
                                        ): void {
                                            if (
                                                blank($get('code'))
                                                && filled($state)
                                            ) {
                                                $set(
                                                    'code',
                                                    Str::upper(
                                                        Str::slug(
                                                            $state,
                                                            '_'
                                                        )
                                                    )
                                                );
                                            }
                                        }
                                    ),

                                Select::make('business_id')
                                    ->label('Business')
                                    ->relationship('business', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(1000)
                            ->placeholder(
                                'Describe the purpose and context of this rating scale...'
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make('Score Range')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('minimum_score')
                                    ->label('Minimum Score')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(1)
                                    ->live()
                                    ->suffix('pts')
                                    ->afterStateUpdated(
                                        function (
                                            Set $set,
                                            Get $get,
                                            mixed $state
                                        ): void {
                                            if (! is_numeric($state)) {
                                                return;
                                            }

                                            $maximum = $get(
                                                'maximum_score'
                                            );

                                            if (
                                                is_numeric($maximum)
                                                && (float) $state
                                                > (float) $maximum
                                            ) {
                                                $set(
                                                    'maximum_score',
                                                    $state
                                                );
                                            }
                                        }
                                    )
                                    ->rules([
                                        fn (Get $get): \Closure =>
                                        function (
                                            string $attribute,
                                            mixed $value,
                                            \Closure $fail
                                        ) use ($get): void {
                                            $maximum = $get(
                                                'maximum_score'
                                            );

                                            if (
                                                is_numeric($value)
                                                && is_numeric($maximum)
                                                && (float) $value
                                                > (float) $maximum
                                            ) {
                                                $fail(
                                                    'The minimum score cannot exceed the maximum score.'
                                                );
                                            }
                                        },
                                    ]),

                                TextInput::make('maximum_score')
                                    ->label('Maximum Score')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(5)
                                    ->live()
                                    ->suffix('pts')
                                    ->afterStateUpdated(
                                        function (
                                            Set $set,
                                            Get $get,
                                            mixed $state
                                        ): void {
                                            if (! is_numeric($state)) {
                                                return;
                                            }

                                            $minimum = $get(
                                                'minimum_score'
                                            );

                                            if (
                                                is_numeric($minimum)
                                                && (float) $state
                                                < (float) $minimum
                                            ) {
                                                $set(
                                                    'minimum_score',
                                                    $state
                                                );
                                            }
                                        }
                                    )
                                    ->rules([
                                        fn (Get $get): \Closure =>
                                        function (
                                            string $attribute,
                                            mixed $value,
                                            \Closure $fail
                                        ) use ($get): void {
                                            $minimum = $get(
                                                'minimum_score'
                                            );

                                            if (
                                                is_numeric($value)
                                                && is_numeric($minimum)
                                                && (float) $value
                                                < (float) $minimum
                                            ) {
                                                $fail(
                                                    'The maximum score cannot be less than the minimum score.'
                                                );
                                            }
                                        },
                                    ]),

                                TextInput::make('decimal_places')
                                    ->label('Decimal Places')
                                    ->required()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(4)
                                    ->default(2)
                                    ->live()
                                    ->suffix('places')
                                    ->hint('Precision for score entry'),
                            ]),
                    ]),

                Section::make('Validity & Defaults')
                    ->icon('heroicon-o-calendar')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('effective_from')
                            ->label('Effective From')
                            ->required()
                            ->native(false)
                            ->displayFormat('M d, Y')
                            ->default(now()->toDateString())
                            ->closeOnDateSelection()
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    Set $set,
                                    Get $get,
                                    mixed $state
                                ): void {
                                    $effectiveTo = $get(
                                        'effective_to'
                                    );

                                    if (
                                        blank($state)
                                        || blank($effectiveTo)
                                    ) {
                                        return;
                                    }

                                    if (
                                        Carbon::parse($state)
                                            ->greaterThan(
                                                Carbon::parse(
                                                    $effectiveTo
                                                )
                                            )
                                    ) {
                                        $set('effective_to', null);
                                    }
                                }
                            ),

                        DatePicker::make('effective_to')
                            ->label('Effective To')
                            ->native(false)
                            ->displayFormat('M d, Y')
                            ->closeOnDateSelection()
                            ->minDate(
                                fn (Get $get): mixed =>
                                $get('effective_from')
                            )
                            ->afterOrEqual('effective_from')
                            ->validationMessages([
                                'after_or_equal' =>
                                    'The end date must be on or after the start date.',
                            ])
                            ->placeholder('No end date'),

                        Toggle::make('is_default')
                            ->label('Default Scale')
                            ->inline(false)
                            ->default(false)
                            ->hintIcon('heroicon-m-star')
                            ->hint(
                                'Only one active default per business/date range'
                            ),
                    ]),

                Section::make('Status')
                    ->icon('heroicon-o-flag')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false)
                            ->default(true)
                            ->onIcon('heroicon-o-check')
                            ->offIcon('heroicon-o-x-mark')
                            ->hint(
                                'Inactive scales are hidden from selection'
                            ),

                        TextInput::make('range_preview')
                            ->label('Range Preview')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder(
                                function (Get $get): string {
                                    $minimum = $get(
                                        'minimum_score'
                                    );

                                    $maximum = $get(
                                        'maximum_score'
                                    );

                                    $decimalPlaces = max(
                                        0,
                                        min(
                                            4,
                                            (int) (
                                                $get(
                                                    'decimal_places'
                                                ) ?? 0
                                            )
                                        )
                                    );

                                    if (
                                        ! is_numeric($minimum)
                                        || ! is_numeric($maximum)
                                    ) {
                                        return '—';
                                    }

                                    $formattedMinimum = number_format(
                                        (float) $minimum,
                                        $decimalPlaces
                                    );

                                    $formattedMaximum = number_format(
                                        (float) $maximum,
                                        $decimalPlaces
                                    );

                                    return "{$formattedMinimum} – {$formattedMaximum}";
                                }
                            )
                            ->suffix('range'),
                    ]),
            ]);
    }
}
