<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDowntimeReasons;

use App\Enums\ProductionDowntimeCategory;
use App\Filament\Resources\ProductionDowntimeReasons\Pages\CreateProductionDowntimeReason;
use App\Filament\Resources\ProductionDowntimeReasons\Pages\EditProductionDowntimeReason;
use App\Filament\Resources\ProductionDowntimeReasons\Pages\ListProductionDowntimeReasons;
use App\Models\Manufacturing\ProductionDowntimeReason;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductionDowntimeReasonResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'factory';
    }

    public static function permissionResource(): string
    {
        return 'production_downtime_reason';
    }

    protected static ?string $model = ProductionDowntimeReason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Shop Floor Setup';

    protected static ?string $navigationLabel = 'Downtime Reasons';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Downtime Reason')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema([
                    TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(50),
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('category')->options(ProductionDowntimeCategory::class)->required(),
                    Toggle::make('requires_approval'),
                    Toggle::make('blocks_completion'),
                    Toggle::make('is_active')->default(true),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Textarea::make('description')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->weight('bold'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category')->badge()->toggleable(),
                IconColumn::make('requires_approval')->boolean()->toggleable(),
                IconColumn::make('blocks_completion')->boolean()->toggleable(),
                IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionDowntimeReasons::route('/'),
            'create' => CreateProductionDowntimeReason::route('/create'),
            'edit' => EditProductionDowntimeReason::route('/{record}/edit'),
        ];
    }
}
