<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionReviewPeriods;

use App\Enums\CommissionReviewPeriodStatus;
use App\Filament\Resources\CommissionReviewPeriods\Pages\CreateCommissionReviewPeriod;
use App\Filament\Resources\CommissionReviewPeriods\Pages\ListCommissionReviewPeriods;
use App\Filament\Resources\CommissionReviewPeriods\Pages\ViewCommissionReviewPeriod;
use App\Models\CommissionReviewPeriod;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionReviewPeriodResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_review_period';
    }

    protected static ?string $model = CommissionReviewPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Review Periods';

    protected static ?int $navigationSort = 77;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(60),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('business_id')->relationship('business', 'name')->searchable()->preload(),
            DatePicker::make('period_start')->required(),
            DatePicker::make('period_end')->required(),
            Select::make('currency_mode')->options(['separate' => 'Separate by currency'])->default('separate')->required(),
            Textarea::make('description')->columnSpanFull(),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->toggleable(),
                TextColumn::make('period_start')->date()->sortable(),
                TextColumn::make('period_end')->date()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('batches_count')->counts('batches')->label('Batches'),
                TextColumn::make('approved_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locked_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(CommissionReviewPeriodStatus::class),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionReviewPeriods::route('/'),
            'create' => CreateCommissionReviewPeriod::route('/create'),
            'view' => ViewCommissionReviewPeriod::route('/{record}'),
        ];
    }
}
