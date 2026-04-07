<?php

namespace App\Filament\PostedShipmentResource\Pages;

use Filament\Pages\Page;

class HistoryNavigate extends Page
{
    protected string $view = 'filament.pages.history-navigate';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-map';
    protected static string|null|\UnitEnum $navigationGroup = 'History';
    protected static ?string $navigationLabel = 'Navigate';
    protected static ?string $title = 'Navigate';
    protected static ?string $slug = 'history/navigate';

    /**
     * Optional: Define data for the navigation cards
     */
    public function getNavigationCards(): array
    {
        return [
            [
                'title' => 'Posted Shipment (Waybill)',
                'description' => 'View and manage historical shipment documents.',
                'icon' => 'heroicon-o-truck',
                'url' => \App\Filament\PostedShipmentResource\PostedShipmentResource::getUrl(),
                'color' => 'primary',
            ],
            [
                'title' => 'Posted Sales Inventory',
                'description' => 'Review inventory movements from posted sales.',
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => \App\Filament\Resources\PostedSalesInventoryResource::getUrl(),
                'color' => 'success',
            ],
            [
                'title' => 'Archived Sales Orders',
                'description' => 'Access deleted or completed sales order archives.',
                'icon' => 'heroicon-o-archive-box',
                'url' => \App\Filament\Resources\ArchivedSalesOrderResource::getUrl(),
                'color' => 'warning',
            ],
        ];
    }
}
