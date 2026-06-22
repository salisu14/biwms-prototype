<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSetSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $matrix = [
            'sales-representative' => [
                'sales.customer.view_any', 'sales.customer.view', 'sales.customer.create', 'sales.customer.update',
                'sales.item.view_any', 'sales.item.view',
                'sales.quote.view_any', 'sales.quote.view', 'sales.quote.create', 'sales.quote.update', 'sales.quote.convert',
                'sales.order.view_any', 'sales.order.view', 'sales.order.create', 'sales.order.update',
                'sales.invoice.view_any', 'sales.invoice.view',
            ],
            'sales-manager' => [
                'sales.customer.view_any', 'sales.customer.view', 'sales.customer.create', 'sales.customer.update', 'sales.customer.delete',
                'sales.item.view_any', 'sales.item.view',
                'sales.quote.view_any', 'sales.quote.view', 'sales.quote.create', 'sales.quote.update', 'sales.quote.delete', 'sales.quote.approve', 'sales.quote.convert',
                'sales.order.view_any', 'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.delete', 'sales.order.approve', 'sales.order.post',
                'sales.invoice.view_any', 'sales.invoice.view',
            ],
            'finance-accountant' => [
                'finance.payment.view_any', 'finance.payment.view', 'finance.payment.create', 'finance.payment.update',
                'finance.payment.post', 'finance.payment.apply', 'finance.payment.reconcile',
                'finance.petty_cash_voucher.view_any', 'finance.petty_cash_voucher.view', 'finance.petty_cash_voucher.create', 'finance.petty_cash_voucher.update',
                'finance.petty_cash_voucher.approve', 'finance.petty_cash_voucher.post',
                'finance.bank_account.view_any', 'finance.bank_account.view',
                'finance.general_journal_batch.view_any', 'finance.general_journal_batch.view', 'finance.general_journal_batch.create', 'finance.general_journal_batch.update',
                'finance.currency_adjustment_ledger.view_any', 'finance.currency_adjustment_ledger.view',
                'finance.customer_ledger_entry.view_any', 'finance.customer_ledger_entry.view',
                'finance.report.view',
                'fixed_asset.view_any', 'fixed_asset.view', 'fixed_asset.depreciate',
                'sales.invoice.view_any', 'sales.invoice.view', 'sales.invoice.update', 'sales.invoice.post',
                'sales.order.view_any', 'sales.order.view',
            ],
            'finance-manager' => [
                'finance.payment.view_any', 'finance.payment.view', 'finance.payment.create', 'finance.payment.update', 'finance.payment.delete',
                'finance.payment.post', 'finance.payment.apply', 'finance.payment.reconcile', 'finance.payment.void',
                'finance.petty_cash_voucher.view_any', 'finance.petty_cash_voucher.view', 'finance.petty_cash_voucher.create', 'finance.petty_cash_voucher.update', 'finance.petty_cash_voucher.delete',
                'finance.petty_cash_voucher.approve', 'finance.petty_cash_voucher.post', 'finance.petty_cash_voucher.cancel',
                'finance.bank_account.view_any', 'finance.bank_account.view', 'finance.bank_account.create', 'finance.bank_account.update',
                'finance.general_journal_batch.view_any', 'finance.general_journal_batch.view', 'finance.general_journal_batch.create', 'finance.general_journal_batch.update', 'finance.general_journal_batch.delete',
                'finance.currency_adjustment_ledger.view_any', 'finance.currency_adjustment_ledger.view', 'finance.currency_adjustment_ledger.create', 'finance.currency_adjustment_ledger.update',
                'finance.customer_ledger_entry.view_any', 'finance.customer_ledger_entry.view',
                'finance.report.view',
                'fixed_asset.view_any', 'fixed_asset.view', 'fixed_asset.create', 'fixed_asset.update', 'fixed_asset.acquire', 'fixed_asset.depreciate', 'fixed_asset.dispose',
                'sales.invoice.view_any', 'sales.invoice.view', 'sales.invoice.create', 'sales.invoice.update', 'sales.invoice.delete', 'sales.invoice.post',
                'sales.order.view_any', 'sales.order.view',
            ],
            'purchasing-agent' => [
                'procurement.vendor.view_any', 'procurement.vendor.view',
                'procurement.purchase_quote.view_any', 'procurement.purchase_quote.view', 'procurement.purchase_quote.create', 'procurement.purchase_quote.update',
                'procurement.purchase_order.view_any', 'procurement.purchase_order.view', 'procurement.purchase_order.create', 'procurement.purchase_order.update',
                'procurement.purchase_receipt.view_any', 'procurement.purchase_receipt.view', 'procurement.purchase_receipt.create',
                'procurement.purchase_invoice.view_any', 'procurement.purchase_invoice.view',
                'procurement.purchase_credit_memo.view_any', 'procurement.purchase_credit_memo.view', 'procurement.purchase_credit_memo.create',
                'procurement.blanket_order.view_any', 'procurement.blanket_order.view',
                'sales.item.view_any', 'sales.item.view',
            ],
            'purchasing-manager' => [
                'procurement.vendor.view_any', 'procurement.vendor.view', 'procurement.vendor.create', 'procurement.vendor.update', 'procurement.vendor.delete',
                'procurement.purchase_quote.view_any', 'procurement.purchase_quote.view', 'procurement.purchase_quote.create', 'procurement.purchase_quote.update', 'procurement.purchase_quote.delete',
                'procurement.purchase_order.view_any', 'procurement.purchase_order.view', 'procurement.purchase_order.create', 'procurement.purchase_order.update', 'procurement.purchase_order.delete',
                'procurement.purchase_receipt.view_any', 'procurement.purchase_receipt.view', 'procurement.purchase_receipt.create', 'procurement.purchase_receipt.update',
                'procurement.purchase_invoice.view_any', 'procurement.purchase_invoice.view', 'procurement.purchase_invoice.create', 'procurement.purchase_invoice.update',
                'procurement.purchase_credit_memo.view_any', 'procurement.purchase_credit_memo.view', 'procurement.purchase_credit_memo.create', 'procurement.purchase_credit_memo.update', 'procurement.purchase_credit_memo.delete',
                'procurement.blanket_order.view_any', 'procurement.blanket_order.view', 'procurement.blanket_order.create', 'procurement.blanket_order.update', 'procurement.blanket_order.delete',
                'sales.item.view_any', 'sales.item.view',
            ],
            'warehouse-worker' => [
                'warehouse.receipt.view_any', 'warehouse.receipt.view', 'warehouse.receipt.create',
                'warehouse.activity.view_any', 'warehouse.activity.view', 'warehouse.activity.create',
                'warehouse.putaway.view_any', 'warehouse.putaway.view', 'warehouse.putaway.create',
                'warehouse.shipment.view_any', 'warehouse.shipment.view', 'warehouse.shipment.create',
                'sales.item.view_any', 'sales.item.view',
                'sales.order.view_any', 'sales.order.view',
            ],
            'warehouse-manager' => [
                'warehouse.receipt.view_any', 'warehouse.receipt.view', 'warehouse.receipt.create', 'warehouse.receipt.update', 'warehouse.receipt.delete',
                'warehouse.activity.view_any', 'warehouse.activity.view', 'warehouse.activity.create', 'warehouse.activity.update', 'warehouse.activity.delete',
                'warehouse.putaway.view_any', 'warehouse.putaway.view', 'warehouse.putaway.create', 'warehouse.putaway.update', 'warehouse.putaway.delete',
                'warehouse.shipment.view_any', 'warehouse.shipment.view', 'warehouse.shipment.create', 'warehouse.shipment.update', 'warehouse.shipment.delete',
                'sales.item.view_any', 'sales.item.view', 'sales.item.update',
                'sales.order.view_any', 'sales.order.view', 'sales.order.post',
            ],
            'factory-operator' => [
                'factory.production_order.planned.view_any', 'factory.production_order.planned.view',
                'factory.production_order.view_any', 'factory.production_order.view',
                'sales.item.view_any', 'sales.item.view',
            ],
            'factory-manager' => [
                'factory.production_order.view_any', 'factory.production_order.view', 'factory.production_order.create', 'factory.production_order.update', 'factory.production_order.delete', 'factory.production_order.post_output', 'factory.production_order.finish', 'factory.production_order.post',
                'factory.production_bom.view_any', 'factory.production_bom.view', 'factory.production_bom.create', 'factory.production_bom.update', 'factory.production_bom.delete',
                'factory.production_bom_version.view_any', 'factory.production_bom_version.view', 'factory.production_bom_version.create', 'factory.production_bom_version.update', 'factory.production_bom_version.delete',
                'factory.routing.view_any', 'factory.routing.view', 'factory.routing.create', 'factory.routing.update', 'factory.routing.delete',
                'factory.routing_version.view_any', 'factory.routing_version.view', 'factory.routing_version.create', 'factory.routing_version.update', 'factory.routing_version.delete',
                'factory.machine_center.view_any', 'factory.machine_center.view', 'factory.machine_center.create', 'factory.machine_center.update', 'factory.machine_center.delete',
                'factory.work_center.view_any', 'factory.work_center.view', 'factory.work_center.create', 'factory.work_center.update', 'factory.work_center.delete',
                'factory.work_center_group.view_any', 'factory.work_center_group.view', 'factory.work_center_group.create', 'factory.work_center_group.update', 'factory.work_center_group.delete',
                'factory.overhead_cost_category.view_any', 'factory.overhead_cost_category.view', 'factory.overhead_cost_category.create', 'factory.overhead_cost_category.update', 'factory.overhead_cost_category.delete',
                'factory.actual_overhead_cost.view_any', 'factory.actual_overhead_cost.view', 'factory.actual_overhead_cost.create', 'factory.actual_overhead_cost.update', 'factory.actual_overhead_cost.delete',
                'factory.report.view',
                'sales.item.view_any', 'sales.item.view', 'sales.item.update',
            ],
            'hr-officer' => [
                'hr.employee.view_any', 'hr.employee.view', 'hr.employee.create', 'hr.employee.update',
                'hr.attendance.view_any', 'hr.attendance.view', 'hr.attendance.create', 'hr.attendance.update', 'hr.attendance.clock',
                'hr.payroll_period.view_any', 'hr.payroll_period.view', 'hr.payroll_period.create', 'hr.payroll_period.update',
                'hr.payroll_document.view_any', 'hr.payroll_document.view', 'hr.payroll_document.create', 'hr.payroll_document.update',
                'hr.pay_code.view_any', 'hr.pay_code.view',
                'hr.payroll_posting_group.view_any', 'hr.payroll_posting_group.view',
            ],
            'hr-manager' => [
                'hr.employee.view_any', 'hr.employee.view', 'hr.employee.create', 'hr.employee.update', 'hr.employee.delete',
                'hr.attendance.view_any', 'hr.attendance.view', 'hr.attendance.create', 'hr.attendance.update', 'hr.attendance.delete', 'hr.attendance.approve', 'hr.attendance.reject', 'hr.attendance.clock',
                'hr.payroll_period.view_any', 'hr.payroll_period.view', 'hr.payroll_period.create', 'hr.payroll_period.update', 'hr.payroll_period.delete',
                'hr.payroll_document.view_any', 'hr.payroll_document.view', 'hr.payroll_document.create', 'hr.payroll_document.update', 'hr.payroll_document.delete',
                'hr.pay_code.view_any', 'hr.pay_code.view', 'hr.pay_code.create', 'hr.pay_code.update',
                'hr.payroll_posting_group.view_any', 'hr.payroll_posting_group.view', 'hr.payroll_posting_group.create', 'hr.payroll_posting_group.update',
            ],
            'project-manager' => [
                'project.capex_project.view_any', 'project.capex_project.view', 'project.capex_project.create', 'project.capex_project.update',
                'factory.production_order.view_any', 'factory.production_order.view',
                'procurement.purchase_order.view_any', 'procurement.purchase_order.view',
                'finance.payment.view_any', 'finance.payment.view',
            ],
            'service-manager' => [
                'service.maintenance_contract.view_any', 'service.maintenance_contract.view', 'service.maintenance_contract.create', 'service.maintenance_contract.update',
                'service.dispatch.view_any', 'service.dispatch.view', 'service.dispatch.create', 'service.dispatch.update',
            ],
            'business-manager' => [
                'sales.order.view_any', 'sales.order.view', 'sales.order.approve', 'sales.order.post',
                'sales.invoice.view_any', 'sales.invoice.view', 'sales.invoice.post',
                'finance.payment.view_any', 'finance.payment.view',
                'finance.customer_ledger_entry.view_any', 'finance.customer_ledger_entry.view',
                'warehouse.shipment.view_any', 'warehouse.shipment.view',
                'factory.production_order.view_any', 'factory.production_order.view',
                'hr.employee.view_any', 'hr.employee.view',
                'procurement.purchase_order.view_any', 'procurement.purchase_order.view',
                'procurement.purchase_invoice.view_any', 'procurement.purchase_invoice.view',
            ],
            'admin' => ['*'],
            'super_admin' => ['*'],
        ];

        $allPermissions = Permission::query()->pluck('name')->all();

        foreach ($matrix as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $role = Role::query()->create(['name' => $roleName, 'guard_name' => 'web']);
            }

            if ($permissions === ['*']) {
                $role->syncPermissions($allPermissions);

                continue;
            }

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
