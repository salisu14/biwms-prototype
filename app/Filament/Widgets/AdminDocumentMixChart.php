<?php

namespace App\Filament\Widgets;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Filament\Widgets\Concerns\AppliesAdminDashboardFilters;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AdminDocumentMixChart extends ChartWidget
{
    use AppliesAdminDashboardFilters;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Current Document Mix';

    protected function getData(): array
    {
        $filters = $this->filters ?? [];

        $openSales = $this->applyCommonFilters(SalesOrder::query(), $filters)
            ->whereIn('status', [
                SalesOrderStatus::DRAFT,
                SalesOrderStatus::PENDING_APPROVAL,
                SalesOrderStatus::APPROVED,
                SalesOrderStatus::RELEASED,
                SalesOrderStatus::PICKING,
                SalesOrderStatus::PACKED,
            ])
            ->count();

        $postedSales = $this->applyCommonFilters(SalesOrder::query(), $filters)
            ->whereIn('status', [
                SalesOrderStatus::SHIPPED,
                SalesOrderStatus::INVOICED,
                SalesOrderStatus::PARTIALLY_INVOICED,
                SalesOrderStatus::CLOSED,
            ])
            ->count();

        $openPurchases = $this->applyCommonFilters(PurchaseOrder::query(), $filters)
            ->whereIn('status', [
                PurchaseOrderStatus::PENDING,
                PurchaseOrderStatus::APPROVED,
                PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ])
            ->count();

        $closedPurchases = $this->applyCommonFilters(PurchaseOrder::query(), $filters)
            ->whereIn('status', [
                PurchaseOrderStatus::RECEIVED,
                PurchaseOrderStatus::INVOICED,
                PurchaseOrderStatus::CLOSED,
            ])
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Document Mix',
                    'data' => [$openSales, $postedSales, $openPurchases, $closedPurchases],
                    'backgroundColor' => [
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b',
                        '#64748b',
                    ],
                ],
            ],
            'labels' => ['Open Sales', 'Posted Sales', 'Open Purchases', 'Closed Purchases'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
