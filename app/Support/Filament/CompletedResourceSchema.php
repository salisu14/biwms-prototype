<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompletedResourceSchema
{
    /**
     * @var array<class-string<Model>, array<int, string>>
     */
    private static array $columnsByModel = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function form(Schema $schema, string $modelClass): Schema
    {
        return $schema->components([
            Section::make(self::title($modelClass))
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema(array_map(
                    fn (string $column): object => self::formComponent($column),
                    self::formColumns($modelClass)
                )),
        ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function infolist(Schema $schema, string $modelClass): Schema
    {
        return $schema->components([
            Section::make(self::title($modelClass))
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema(array_map(
                    fn (string $column): TextEntry => self::textEntry($column),
                    self::visibleColumns($modelClass)
                )),
        ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function table(Table $table, string $modelClass): Table
    {
        $columns = self::tableColumns($modelClass);

        return $table
            ->defaultSort(self::defaultSortColumn($modelClass), 'desc')
            ->columns(array_map(
                fn (string $column): object => self::tableColumn($column),
                $columns
            ))
            ->filters(self::tableFilters($modelClass));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    public static function tableColumnNames(string $modelClass): array
    {
        return self::tableColumns($modelClass);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private static function formColumns(string $modelClass): array
    {
        return array_values(array_filter(
            self::visibleColumns($modelClass),
            fn (string $column): bool => ! in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'], true)
        ));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private static function visibleColumns(string $modelClass): array
    {
        return array_values(array_filter(
            self::columns($modelClass),
            fn (string $column): bool => ! self::isSensitiveColumn($column)
        ));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private static function tableColumns(string $modelClass): array
    {
        $columns = self::visibleColumns($modelClass);
        $preferred = [
            'number',
            'document_number',
            'requisition_no',
            'vacancy_no',
            'application_no',
            'candidate_no',
            'offer_no',
            'employee_number',
            'full_name',
            'name',
            'title',
            'status',
            'stage',
            'employee_id',
            'candidate_id',
            'vacancy_id',
            'department_id',
            'posting_date',
            'application_date',
            'issue_date',
            'expiry_date',
            'due_date',
            'created_at',
        ];

        $ordered = array_values(array_intersect($preferred, $columns));

        foreach ($columns as $column) {
            if (count($ordered) >= 9) {
                break;
            }

            if (! in_array($column, $ordered, true) && ! Str::endsWith($column, ['_notes', '_description', '_metadata'])) {
                $ordered[] = $column;
            }
        }

        return $ordered ?: array_slice($columns, 0, 8);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private static function columns(string $modelClass): array
    {
        if (isset(self::$columnsByModel[$modelClass])) {
            return self::$columnsByModel[$modelClass];
        }

        /** @var Model $model */
        $model = new $modelClass;

        return self::$columnsByModel[$modelClass] = DB::getSchemaBuilder()->getColumnListing($model->getTable());
    }

    private static function formComponent(string $column): object
    {
        $label = self::label($column);

        if (self::isInternalColumn($column)) {
            return Hidden::make($column);
        }

        if (self::isBooleanColumn($column)) {
            return Toggle::make($column)->label($label);
        }

        if (self::isDateTimeColumn($column)) {
            return DateTimePicker::make($column)
                ->label($label)
                ->disabled(self::isGuardedLifecycleColumn($column))
                ->dehydrated(! self::isGuardedLifecycleColumn($column));
        }

        if (self::isDateColumn($column)) {
            return DatePicker::make($column)->label($label);
        }

        if (self::isLongTextColumn($column)) {
            return Textarea::make($column)
                ->label($label)
                ->rows(3)
                ->columnSpanFull()
                ->disabled(self::isGuardedLifecycleColumn($column))
                ->dehydrated(! self::isGuardedLifecycleColumn($column));
        }

        return TextInput::make($column)
            ->label($label)
            ->numeric(self::isNumericColumn($column))
            ->maxLength(255)
            ->disabled(self::isGuardedLifecycleColumn($column))
            ->dehydrated(! self::isGuardedLifecycleColumn($column));
    }

    private static function textEntry(string $column): TextEntry
    {
        $entry = TextEntry::make($column)
            ->label(self::label($column))
            ->placeholder('—');

        if (self::isStatusColumn($column)) {
            $entry->badge();
        }

        if (self::isDateTimeColumn($column)) {
            $entry->dateTime();
        } elseif (self::isDateColumn($column)) {
            $entry->date();
        } elseif (self::isMoneyColumn($column)) {
            $entry->numeric(decimalPlaces: 2);
        }

        if (self::isLongTextColumn($column)) {
            $entry->columnSpanFull();
        }

        return $entry;
    }

    private static function tableColumn(string $column): object
    {
        if (self::isBooleanColumn($column)) {
            return IconColumn::make($column)
                ->label(self::label($column))
                ->boolean()
                ->sortable();
        }

        $tableColumn = TextColumn::make($column)
            ->label(self::label($column))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: self::isSecondaryTableColumn($column));

        if (self::isSearchableColumn($column)) {
            $tableColumn->searchable();
        }

        if (self::isStatusColumn($column)) {
            $tableColumn->badge();
        }

        if (self::isDateTimeColumn($column)) {
            $tableColumn->dateTime();
        } elseif (self::isDateColumn($column)) {
            $tableColumn->date();
        } elseif (self::isMoneyColumn($column)) {
            $tableColumn->numeric(decimalPlaces: 2);
        }

        return $tableColumn;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, SelectFilter>
     */
    private static function tableFilters(string $modelClass): array
    {
        $filters = [];
        $columns = self::visibleColumns($modelClass);

        foreach (['status', 'stage', 'is_active', 'department_id', 'employee_id', 'candidate_id', 'vacancy_id'] as $column) {
            if (in_array($column, $columns, true)) {
                $filters[] = SelectFilter::make($column);
            }
        }

        return $filters;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function title(string $modelClass): string
    {
        return Str::headline(class_basename($modelClass));
    }

    private static function label(string $column): string
    {
        return Str::headline(str_replace('_id', '', $column));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function defaultSortColumn(string $modelClass): string
    {
        $columns = self::columns($modelClass);

        foreach (['created_at', 'updated_at', 'id'] as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return $columns[0] ?? 'id';
    }

    private static function isInternalColumn(string $column): bool
    {
        return $column === 'id' || Str::endsWith($column, ['_token', '_signature']);
    }

    private static function isSensitiveColumn(string $column): bool
    {
        return Str::contains($column, ['password', 'secret', 'token', 'recovery_code']);
    }

    private static function isGuardedLifecycleColumn(string $column): bool
    {
        return in_array($column, [
            'status',
            'stage',
            'submitted_at',
            'approved_at',
            'rejected_at',
            'issued_at',
            'accepted_at',
            'declined_at',
            'converted_at',
            'completed_at',
            'implemented_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ], true) || Str::endsWith($column, ['_by']);
    }

    private static function isStatusColumn(string $column): bool
    {
        return in_array($column, ['status', 'stage', 'result', 'recommendation', 'decision'], true);
    }

    private static function isBooleanColumn(string $column): bool
    {
        return Str::startsWith($column, ['is_', 'has_', 'requires_']) || Str::endsWith($column, ['_required', '_completed']);
    }

    private static function isDateColumn(string $column): bool
    {
        return Str::endsWith($column, '_date') || in_array($column, ['date'], true);
    }

    private static function isDateTimeColumn(string $column): bool
    {
        return Str::endsWith($column, '_at') || Str::endsWith($column, '_time');
    }

    private static function isNumericColumn(string $column): bool
    {
        return Str::endsWith($column, ['_id', '_count', '_days', '_hours', '_score', '_amount', '_rate', '_percentage'])
            || Str::contains($column, ['quantity', 'salary', 'headcount', 'weight', 'score', 'amount', 'percent']);
    }

    private static function isMoneyColumn(string $column): bool
    {
        return Str::contains($column, ['amount', 'salary', 'cost', 'pay', 'rate']);
    }

    private static function isLongTextColumn(string $column): bool
    {
        return Str::contains($column, ['description', 'notes', 'reason', 'comment', 'metadata', 'criteria', 'summary', 'remarks']);
    }

    private static function isSearchableColumn(string $column): bool
    {
        return Str::contains($column, ['number', 'name', 'title', 'email', 'phone', 'code', 'status', 'stage']);
    }

    private static function isSecondaryTableColumn(string $column): bool
    {
        return Str::endsWith($column, ['_id', '_by', '_at'])
            || Str::contains($column, ['metadata', 'notes', 'description']);
    }
}
