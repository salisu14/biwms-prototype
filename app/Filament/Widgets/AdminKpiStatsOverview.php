<?php

namespace App\Filament\Widgets;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Filament\Widgets\Concerns\AppliesAdminDashboardFilters;
use App\Models\MaintenanceContract;
use App\Models\MaintenanceContractSchedule;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminKpiStatsOverview extends BaseWidget
{
    use AppliesAdminDashboardFilters;
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $filters = $this->filters ?? [];

        $openSalesOrders = $this->applyCommonFilters(SalesOrder::query(), $filters)
            ->whereIn('status', [
                SalesOrderStatus::APPROVED,
                SalesOrderStatus::RELEASED,
                SalesOrderStatus::PICKING,
                SalesOrderStatus::PACKED,
            ])
            ->count();

        $pendingPurchaseOrders = $this->applyCommonFilters(PurchaseOrder::query(), $filters)
            ->whereIn('status', [
                PurchaseOrderStatus::PENDING,
                PurchaseOrderStatus::APPROVED,
                PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ])
            ->count();

        $overdueDispatches = $this->applyCommonFilters(MaintenanceContractSchedule::query(), $filters, 'next_service_date')
            ->where('is_active', true)
            ->whereDate('next_service_date', '<', now()->toDateString())
            ->count();

        $overdueReceivables = $this->applyCommonFilters(SalesInvoice::query(), $filters, 'invoice_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $openProductionOrders = $this->applyCommonFilters(ProductionOrder::query(), $filters)
            ->whereIn('status', ['RELEASED', 'PLANNED', 'IN_PROGRESS'])
            ->count();

        $scheduledServiceContracts = $this->applyCommonFilters(MaintenanceContract::query(), $filters, 'start_date')
            ->where('status', 'active')
            ->count();

        $pendingPayments = $this->applyCommonFilters(Payment::query(), $filters, 'payment_date')
            ->where('status', 'PENDING')
            ->count();

        $openPurchaseInvoices = $this->applyCommonFilters(PurchaseInvoice::query(), $filters, 'posting_date')
            ->where('paid_in_full', false)
            ->where('cancelled', false)
            ->count();

        return [
            Stat::make('Open Sales Orders', $openSalesOrders)
                ->description('Orders in fulfillment flow')
                ->color('info'),
            Stat::make('Pending Purchase Orders', $pendingPurchaseOrders)
                ->description('Awaiting receipt/invoice')
                ->color('warning'),
            Stat::make('Overdue Dispatches', $overdueDispatches)
                ->description('Service visits overdue')
                ->color('danger'),
            Stat::make('Overdue Receivables', $overdueReceivables)
                ->description('Invoices past due date')
                ->color('success'),
            Stat::make('Open Production Orders', $openProductionOrders)
                ->description('Manufacturing load')
                ->color('warning'),
            Stat::make('Active Service Contracts', $scheduledServiceContracts)
                ->description('Service commitments')
                ->color('info'),
            Stat::make('Pending Payments', $pendingPayments)
                ->description('Cash actions pending')
                ->color('danger'),
            Stat::make('Open Purchase Invoices', $openPurchaseInvoices)
                ->description('Outstanding payables')
                ->color('gray'),
        ];
    }
}
