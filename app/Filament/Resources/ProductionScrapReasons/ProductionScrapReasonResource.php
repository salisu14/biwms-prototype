<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionScrapReasons;

use App\Enums\ProductionScrapPostingTreatment;
use App\Enums\ProductionScrapStage;
use App\Filament\Resources\ProductionScrapReasons\Pages\CreateProductionScrapReason;
use App\Filament\Resources\ProductionScrapReasons\Pages\EditProductionScrapReason;
use App\Filament\Resources\ProductionScrapReasons\Pages\ListProductionScrapReasons;
use App\Models\Manufacturing\ProductionScrapReason;
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

class ProductionScrapReasonResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'factory';
    }

    public static function permissionResource(): string
    {
        return 'production_scrap_reason';
    }

    protected static ?string $model = ProductionScrapReason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Shop Floor Setup';

    protected static ?string $navigationLabel = 'Scrap Reasons';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Scrap Reason')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema([
                    TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(50),
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('stage')->options(ProductionScrapStage::class)->required(),
                    Select::make('default_posting_treatment')->options(ProductionScrapPostingTreatment::class)->required(),
                    Toggle::make('requires_approval'),
                    Toggle::make('requires_quality_review'),
                    Toggle::make('recoverable'),
                    Toggle::make('reworkable'),
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
                TextColumn::make('stage')->badge()->toggleable(),
                TextColumn::make('default_posting_treatment')->label('Treatment')->badge()->toggleable(),
                IconColumn::make('requires_approval')->boolean()->toggleable(),
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
            'index' => ListProductionScrapReasons::route('/'),
            'create' => CreateProductionScrapReason::route('/create'),
            'edit' => EditProductionScrapReason::route('/{record}/edit'),
        ];
    }
}
