<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $guard = 'web';

        $legacyPermissions = [
            'user_management_access',
            'admin_dashboard_access',
            'notification_access',
            'customer_access', 'customer_show', 'customer_create', 'customer_edit', 'customer_delete',
            'item_access', 'item_show', 'item_create', 'item_edit', 'item_delete',
            'sales_order_access', 'sales_order_show', 'sales_order_create', 'sales_order_edit', 'sales_order_delete',
            'sales_quote_access', 'sales_quote_show', 'sales_quote_create', 'sales_quote_edit', 'sales_quote_delete',
            'sales_invoice_access', 'sales_invoice_show', 'sales_invoice_create', 'sales_invoice_edit', 'sales_invoice_delete',
            'bank_access', 'bank_show', 'bank_create', 'bank_edit', 'bank_delete',
            'payment_access', 'payment_show', 'payment_create', 'payment_edit', 'payment_delete',
            'employee_access', 'employee_show', 'employee_create', 'employee_edit', 'employee_delete',
        ];

        $bcPermissions = [
            // Sales
            'sales.customer.view_any', 'sales.customer.view', 'sales.customer.create', 'sales.customer.update', 'sales.customer.delete',
            'sales.item.view_any', 'sales.item.view', 'sales.item.create', 'sales.item.update', 'sales.item.delete',
            'sales.quote.view_any', 'sales.quote.view', 'sales.quote.create', 'sales.quote.update', 'sales.quote.delete',
            'sales.quote.approve', 'sales.quote.convert',
            'sales.order.view_any', 'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.delete',
            'sales.order.approve', 'sales.order.post',
            'sales.invoice.view_any', 'sales.invoice.view', 'sales.invoice.create', 'sales.invoice.update', 'sales.invoice.delete', 'sales.invoice.post',

            // Finance
            'finance.payment.view_any', 'finance.payment.view', 'finance.payment.create', 'finance.payment.update', 'finance.payment.delete',
            'finance.payment.post', 'finance.payment.apply', 'finance.payment.reconcile', 'finance.payment.void',
            'finance.petty_cash_voucher.view_any', 'finance.petty_cash_voucher.view', 'finance.petty_cash_voucher.create', 'finance.petty_cash_voucher.update', 'finance.petty_cash_voucher.delete',
            'finance.petty_cash_voucher.approve', 'finance.petty_cash_voucher.post', 'finance.petty_cash_voucher.cancel',
            'finance.bank_account.view_any', 'finance.bank_account.view', 'finance.bank_account.create', 'finance.bank_account.update', 'finance.bank_account.delete',
            'finance.general_journal_batch.view_any', 'finance.general_journal_batch.view', 'finance.general_journal_batch.create', 'finance.general_journal_batch.update', 'finance.general_journal_batch.delete',
            'finance.currency_adjustment_ledger.view_any', 'finance.currency_adjustment_ledger.view', 'finance.currency_adjustment_ledger.create', 'finance.currency_adjustment_ledger.update', 'finance.currency_adjustment_ledger.delete',
            'finance.customer_ledger_entry.view_any', 'finance.customer_ledger_entry.view', 'finance.customer_ledger_entry.create', 'finance.customer_ledger_entry.update', 'finance.customer_ledger_entry.delete',

            // Warehouse
            'warehouse.receipt.view_any', 'warehouse.receipt.view', 'warehouse.receipt.create', 'warehouse.receipt.update', 'warehouse.receipt.delete',
            'warehouse.activity.view_any', 'warehouse.activity.view', 'warehouse.activity.create', 'warehouse.activity.update', 'warehouse.activity.delete',
            'warehouse.putaway.view_any', 'warehouse.putaway.view', 'warehouse.putaway.create', 'warehouse.putaway.update', 'warehouse.putaway.delete',
            'warehouse.shipment.view_any', 'warehouse.shipment.view', 'warehouse.shipment.create', 'warehouse.shipment.update', 'warehouse.shipment.delete',

            // Factory
            'factory.production_order.view_any', 'factory.production_order.view', 'factory.production_order.create', 'factory.production_order.update', 'factory.production_order.delete',
            'factory.production_bom.view_any', 'factory.production_bom.view', 'factory.production_bom.create', 'factory.production_bom.update', 'factory.production_bom.delete',
            'factory.production_bom_version.view_any', 'factory.production_bom_version.view', 'factory.production_bom_version.create', 'factory.production_bom_version.update', 'factory.production_bom_version.delete',
            'factory.routing.view_any', 'factory.routing.view', 'factory.routing.create', 'factory.routing.update', 'factory.routing.delete',
            'factory.routing_version.view_any', 'factory.routing_version.view', 'factory.routing_version.create', 'factory.routing_version.update', 'factory.routing_version.delete',
            'factory.machine_center.view_any', 'factory.machine_center.view', 'factory.machine_center.create', 'factory.machine_center.update', 'factory.machine_center.delete',
            'factory.work_center.view_any', 'factory.work_center.view', 'factory.work_center.create', 'factory.work_center.update', 'factory.work_center.delete',
            'factory.work_center_group.view_any', 'factory.work_center_group.view', 'factory.work_center_group.create', 'factory.work_center_group.update', 'factory.work_center_group.delete',
            'factory.overhead_cost_category.view_any', 'factory.overhead_cost_category.view', 'factory.overhead_cost_category.create', 'factory.overhead_cost_category.update', 'factory.overhead_cost_category.delete',
            'factory.actual_overhead_cost.view_any', 'factory.actual_overhead_cost.view', 'factory.actual_overhead_cost.create', 'factory.actual_overhead_cost.update', 'factory.actual_overhead_cost.delete',

            // HR
            'hr.employee.view_any', 'hr.employee.view', 'hr.employee.create', 'hr.employee.update', 'hr.employee.delete',
            'hr.attendance.view_any', 'hr.attendance.view', 'hr.attendance.create', 'hr.attendance.update', 'hr.attendance.delete',
            'hr.attendance.approve', 'hr.attendance.reject', 'hr.attendance.clock',
            'hr.payroll_period.view_any', 'hr.payroll_period.view', 'hr.payroll_period.create', 'hr.payroll_period.update', 'hr.payroll_period.delete',
            'hr.payroll_document.view_any', 'hr.payroll_document.view', 'hr.payroll_document.create', 'hr.payroll_document.update', 'hr.payroll_document.delete',
            'hr.payroll_posting_group.view_any', 'hr.payroll_posting_group.view', 'hr.payroll_posting_group.create', 'hr.payroll_posting_group.update', 'hr.payroll_posting_group.delete',
            'hr.pay_code.view_any', 'hr.pay_code.view', 'hr.pay_code.create', 'hr.pay_code.update', 'hr.pay_code.delete',

            // Procurement
            'procurement.vendor.view_any', 'procurement.vendor.view', 'procurement.vendor.create', 'procurement.vendor.update', 'procurement.vendor.delete',
            'procurement.purchase_quote.view_any', 'procurement.purchase_quote.view', 'procurement.purchase_quote.create', 'procurement.purchase_quote.update', 'procurement.purchase_quote.delete',
            'procurement.purchase_order.view_any', 'procurement.purchase_order.view', 'procurement.purchase_order.create', 'procurement.purchase_order.update', 'procurement.purchase_order.delete',
            'procurement.purchase_receipt.view_any', 'procurement.purchase_receipt.view', 'procurement.purchase_receipt.create', 'procurement.purchase_receipt.update', 'procurement.purchase_receipt.delete',
            'procurement.purchase_invoice.view_any', 'procurement.purchase_invoice.view', 'procurement.purchase_invoice.create', 'procurement.purchase_invoice.update', 'procurement.purchase_invoice.delete',
            'procurement.purchase_credit_memo.view_any', 'procurement.purchase_credit_memo.view', 'procurement.purchase_credit_memo.create', 'procurement.purchase_credit_memo.update', 'procurement.purchase_credit_memo.delete',
            'procurement.blanket_order.view_any', 'procurement.blanket_order.view', 'procurement.blanket_order.create', 'procurement.blanket_order.update', 'procurement.blanket_order.delete',

            // Project
            'project.capex_project.view_any', 'project.capex_project.view', 'project.capex_project.create', 'project.capex_project.update', 'project.capex_project.delete',

            // Service
            'service.maintenance_contract.view_any', 'service.maintenance_contract.view', 'service.maintenance_contract.create', 'service.maintenance_contract.update', 'service.maintenance_contract.delete',
            'service.dispatch.view_any', 'service.dispatch.view', 'service.dispatch.create', 'service.dispatch.update', 'service.dispatch.delete',

            // Backward compatible procurement keys
            'view:any:vendor', 'view:vendor', 'create:vendor', 'edit:vendor', 'delete:vendor',
            'view:any:purchase_quote', 'view:purchase_quote', 'create:purchase_quote', 'edit:purchase_quote', 'delete:purchase_quote',
            'view:any:purchase_order', 'view:purchase_order', 'create:purchase_order', 'edit:purchase_order', 'delete:purchase_order',
            'view:any:purchase_receipt', 'view:purchase_receipt', 'create:purchase_receipt', 'edit:purchase_receipt', 'delete:purchase_receipt',
            'view:any:purchase_invoice', 'view:purchase_invoice', 'create:purchase_invoice', 'edit:purchase_invoice', 'delete:purchase_invoice',
            'view:any:purchase_credit_memo', 'view:purchase_credit_memo', 'create:purchase_credit_memo', 'edit:purchase_credit_memo', 'delete:purchase_credit_memo',
            'view:any:blanket_order', 'view:blanket_order', 'create:blanket_order', 'edit:blanket_order', 'delete:blanket_order',
            'view:any:capex_project', 'view:capex_project', 'create:capex_project', 'edit:capex_project', 'delete:capex_project',
            'view:any:maintenance_contract', 'view:maintenance_contract', 'create:maintenance_contract', 'edit:maintenance_contract', 'delete:maintenance_contract',
            'view:any:service_dispatch', 'view:service_dispatch', 'create:service_dispatch', 'edit:service_dispatch', 'delete:service_dispatch',

            // Backward compatible colon keys
            'view:any:customer', 'view:customer', 'create:customer', 'edit:customer', 'delete:customer',
            'view:any:item', 'view:item', 'create:item', 'edit:item', 'delete:item',
            'view:any:sales_quote', 'view:sales_quote', 'create:sales_quote', 'edit:sales_quote', 'delete:sales_quote', 'approve:sales_quote', 'convert:sales_quote',
            'view:any:sales_order', 'view:sales_order', 'create:sales_order', 'edit:sales_order', 'delete:sales_order', 'approve:sales_order', 'post:sales_order',
            'view:any:sales_invoice', 'view:sales_invoice', 'create:sales_invoice', 'edit:sales_invoice', 'delete:sales_invoice', 'post:sales_invoice',
            'view:any:order', 'create:order', 'edit:order', 'delete:order', 'approve:order', 'post:order',
        ];

        $all = collect($legacyPermissions)
            ->merge($bcPermissions)
            ->unique()
            ->values();

        $payload = $all->map(fn (string $name): array => [
            'name' => $name,
            'guard_name' => $guard,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Permission::query()->upsert($payload, ['name', 'guard_name'], ['updated_at']);
    }
}
