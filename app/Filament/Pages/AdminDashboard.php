<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminCashflowChart;
use App\Filament\Widgets\AdminDocumentMixChart;
use App\Filament\Widgets\AdminKpiStatsOverview;
use App\Filament\Widgets\AdminOpsTrendChart;
use App\Models\Business;
use App\Models\Factory;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Panel;
use Filament\Schemas\Schema;

class AdminDashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Role Center';

    protected static ?string $title = 'Business Manager Role Center';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getWidgets(): array
    {
        return [
            AdminKpiStatsOverview::class,
            AdminOpsTrendChart::class,
            AdminDocumentMixChart::class,
            AdminCashflowChart::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Period')
                    ->options([
                        'this_month' => 'This Month',
                        'this_quarter' => 'This Quarter',
                        'ytd' => 'Year to Date',
                        'last_30_days' => 'Last 30 Days',
                        'last_90_days' => 'Last 90 Days',
                        'last_180_days' => 'Last 180 Days',
                    ])
                    ->default('last_90_days')
                    ->native(false),
                Select::make('company_code')
                    ->label('Company')
                    ->options(
                        Business::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'code')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('All Companies'),
                Select::make('factory_code')
                    ->label('Factory')
                    ->options(
                        Factory::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'code')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('All Factories'),
            ]);
    }
}
