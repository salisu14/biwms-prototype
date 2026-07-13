<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Schemas;

use App\Models\PerformanceRatingScale;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PerformanceAppraisalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Identification')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Template Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g. ANNUAL_REVIEW_2026')
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
                                    ->label('Template Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(
                                        'e.g. Annual Performance Review 2026'
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
                                    ->relationship(
                                        name: 'business',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (
                                            Builder $query
                                        ): Builder => $query
                                            ->orderBy('name')
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(
                                        function (Set $set): void {
                                            $set(
                                                'applicable_department_id',
                                                null
                                            );
                                            $set(
                                                'applicable_position_id',
                                                null
                                            );
                                            $set(
                                                'applicable_grade_id',
                                                null
                                            );
                                            $set(
                                                'rating_scale_id',
                                                null
                                            );
                                        }
                                    ),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(1000)
                            ->placeholder(
                                'Describe the purpose and scope of this appraisal template...'
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make('Applicability')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make(
                                    'applicable_department_id'
                                )
                                    ->label('Department')
                                    ->relationship(
                                        name: 'department',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (
                                            Builder $query,
                                            Get $get
                                        ): Builder {
                                            return $query
                                                ->when(
                                                    filled(
                                                        $get(
                                                            'business_id'
                                                        )
                                                    ),
                                                    fn (
                                                        Builder $query
                                                    ): Builder =>
                                                    $query->where(
                                                        'business_id',
                                                        $get(
                                                            'business_id'
                                                        )
                                                    )
                                                )
                                                ->orderBy('name');
                                        }
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('All departments')
                                    ->hint(
                                        'Leave blank for company-wide'
                                    )
                                    ->disabled(
                                        fn (Get $get): bool =>
                                        blank(
                                            $get('business_id')
                                        )
                                    ),

//                                Select::make(
//                                    'applicable_position_id'
//                                )
//                                    ->label('Position')
//                                    ->relationship(
//                                        name: 'position',
//                                        titleAttribute: 'name',
//                                        modifyQueryUsing: function (
//                                            Builder $query,
//                                            Get $get
//                                        ): Builder {
//                                            return $query
//                                                ->when(
//                                                    filled(
//                                                        $get(
//                                                            'business_id'
//                                                        )
//                                                    ),
//                                                    fn (
//                                                        Builder $query
//                                                    ): Builder =>
//                                                    $query->where(
//                                                        'business_id',
//                                                        $get(
//                                                            'business_id'
//                                                        )
//                                                    )
//                                                )
//                                                ->orderBy('name');
//                                        }
//                                    )
//                                    ->searchable()
//                                    ->preload()
//                                    ->native(false)
//                                    ->placeholder('All positions')
//                                    ->disabled(
//                                        fn (Get $get): bool =>
//                                        blank(
//                                            $get('business_id')
//                                        )
//                                    ),

                                Select::make('applicable_position_id')
                                    ->label('Position')
                                    ->disabled()
                                    ->placeholder('Position setup is not available'),
//
//                                Select::make(
//                                    'applicable_grade_id'
//                                )
//                                    ->label('Grade')
//                                    ->relationship(
//                                        name: 'grade',
//                                        titleAttribute: 'name',
//                                        modifyQueryUsing: function (
//                                            Builder $query,
//                                            Get $get
//                                        ): Builder {
//                                            return $query
//                                                ->when(
//                                                    filled(
//                                                        $get(
//                                                            'business_id'
//                                                        )
//                                                    ),
//                                                    fn (
//                                                        Builder $query
//                                                    ): Builder =>
//                                                    $query->where(
//                                                        'business_id',
//                                                        $get(
//                                                            'business_id'
//                                                        )
//                                                    )
//                                                )
//                                                ->orderBy('name');
//                                        }
//                                    )
//                                    ->searchable()
//                                    ->preload()
//                                    ->native(false)
//                                    ->placeholder('All grades')
//                                    ->disabled(
//                                        fn (Get $get): bool =>
//                                        blank(
//                                            $get('business_id')
//                                        )
//                                    ),
                            ]),

                        Select::make(
                            'applicable_employment_type'
                        )
                            ->label('Employment Type')
                            ->options([
                                'full_time' => 'Full Time',
                                'part_time' => 'Part Time',
                                'contract' => 'Contract',
                                'intern' => 'Intern',
                                'probation' => 'Probation',
                            ])
                            ->multiple()
                            ->native(false)
                            ->placeholder(
                                'All employment types'
                            ),
                    ]),

                Section::make('Rating Scale')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Select::make('rating_scale_id')
                            ->label(
                                'Performance Rating Scale'
                            )
                            ->relationship(
                                name: 'scale',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (
                                    Builder $query,
                                    Get $get
                                ): Builder {
                                    return $query
                                        ->where('is_active', true)
                                        ->when(
                                            filled(
                                                $get('business_id')
                                            ),
                                            fn (
                                                Builder $query
                                            ): Builder =>
                                            $query->where(
                                                'business_id',
                                                $get(
                                                    'business_id'
                                                )
                                            )
                                        )
                                        ->orderBy('name');
                                }
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (
                                    PerformanceRatingScale $record
                                ): string =>
                                    "{$record->name} "
                                    . "({$record->minimum_score} – "
                                    . "{$record->maximum_score})"
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(
                                fn (Get $get): bool =>
                                blank($get('business_id'))
                            )
                            ->helperText(
                                function (
                                    mixed $state
                                ): string {
                                    if (blank($state)) {
                                        return 'Select the rating scale used to score this appraisal.';
                                    }

                                    return PerformanceRatingScale::query()
                                        ->whereKey($state)
                                        ->value('description')
                                        ?? 'Selected rating scale.';
                                }
                            ),
                    ]),

                Section::make('Component Weights')
                    ->icon('heroicon-o-calculator')
                    ->columns(3)
                    ->schema([
                        TextInput::make(
                            'goal_weight_percent'
                        )
                            ->label('Goal Weight')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(50)
                            ->live()
                            ->suffix('%')
                            ->rules([
                                self::weightTotalRule(),
                            ]),

                        TextInput::make(
                            'competency_weight_percent'
                        )
                            ->label('Competency Weight')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(30)
                            ->live()
                            ->suffix('%')
                            ->rules([
                                self::weightTotalRule(),
                            ]),

                        TextInput::make(
                            'other_weight_percent'
                        )
                            ->label('Other Weight')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(20)
                            ->live()
                            ->suffix('%')
                            ->rules([
                                self::weightTotalRule(),
                            ]),

                        TextInput::make('weight_total')
                            ->label('Total Weight')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('%')
                            ->placeholder(
                                fn (Get $get): string =>
                                number_format(
                                    self::calculateWeightTotal(
                                        $get
                                    ),
                                    2
                                )
                            )
                            ->columnSpanFull()
                            ->hint('Must equal exactly 100%')
                            ->hintColor(
                                fn (Get $get): string =>
                                self::weightsAreValid($get)
                                    ? 'success'
                                    : 'danger'
                            ),
                    ]),

                Section::make('Comment Requirements')
                    ->icon(
                        'heroicon-o-chat-bubble-left-right'
                    )
                    ->columns(3)
                    ->schema([
                        Toggle::make(
                            'require_self_comment'
                        )
                            ->label(
                                'Require Self Comment'
                            )
                            ->inline(false)
                            ->default(true)
                            ->hint(
                                'Employee must provide self-assessment comments'
                            ),

                        Toggle::make(
                            'require_manager_comment'
                        )
                            ->label(
                                'Require Manager Comment'
                            )
                            ->inline(false)
                            ->default(true)
                            ->hint(
                                'Manager must provide evaluation comments'
                            ),

                        Toggle::make(
                            'require_final_comment'
                        )
                            ->label(
                                'Require Final Comment'
                            )
                            ->inline(false)
                            ->default(true)
                            ->hint(
                                'Final reviewer must provide closing comments'
                            ),
                    ]),

                Section::make('Validity & Version')
                    ->icon('heroicon-o-calendar')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('effective_from')
                            ->label('Effective From')
                            ->required()
                            ->native(false)
                            ->displayFormat('M d, Y')
                            ->default(
                                now()->toDateString()
                            )
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
                                        $set(
                                            'effective_to',
                                            null
                                        );
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
                            ->afterOrEqual(
                                'effective_from'
                            )
                            ->validationMessages([
                                'after_or_equal' =>
                                    'The end date must be on or after the start date.',
                            ])
                            ->placeholder('No end date'),

                        TextInput::make('version')
                            ->label('Version')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->suffix('v'),

                        Toggle::make(
                            'allow_not_applicable'
                        )
                            ->label('Allow N/A')
                            ->inline(false)
                            ->default(false)
                            ->hint(
                                'Allow items to be marked as not applicable'
                            ),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false)
                            ->default(true)
                            ->onIcon(
                                'heroicon-o-check'
                            )
                            ->offIcon(
                                'heroicon-o-x-mark'
                            ),
                    ]),
            ]);
    }

    private static function calculateWeightTotal(
        Get $get
    ): float {
        $goal = (float) (
            $get('goal_weight_percent') ?? 0
        );

        $competency = (float) (
            $get('competency_weight_percent') ?? 0
        );

        $other = (float) (
            $get('other_weight_percent') ?? 0
        );

        return $goal + $competency + $other;
    }

    private static function weightsAreValid(
        Get $get
    ): bool {
        return abs(
                self::calculateWeightTotal($get) - 100.0
            ) < 0.0001;
    }

    private static function weightTotalRule(): \Closure
    {
        return fn (Get $get): \Closure =>
        function (
            string $attribute,
            mixed $value,
            \Closure $fail
        ) use ($get): void {
            if (! self::weightsAreValid($get)) {
                $fail(
                    'Goal, competency, and other weights must total exactly 100%.'
                );
            }
        };
    }
}
