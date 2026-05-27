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
            'factory.routing.view_any', 'factory.routing.view', 'factory.routing.create', 'factory.routing.update', 'factory.routing.delete',
            'factory.machine_center.view_any', 'factory.machine_center.view', 'factory.machine_center.create', 'factory.machine_center.update', 'factory.machine_center.delete',

            // HR
            'hr.employee.view_any', 'hr.employee.view', 'hr.employee.create', 'hr.employee.update', 'hr.employee.delete',
            'hr.payroll_document.view_any', 'hr.payroll_document.view', 'hr.payroll_document.create', 'hr.payroll_document.update', 'hr.payroll_document.delete',
            'hr.payroll_posting_group.view_any', 'hr.payroll_posting_group.view', 'hr.payroll_posting_group.create', 'hr.payroll_posting_group.update', 'hr.payroll_posting_group.delete',
            'hr.pay_code.view_any', 'hr.pay_code.view', 'hr.pay_code.create', 'hr.pay_code.update', 'hr.pay_code.delete',

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
