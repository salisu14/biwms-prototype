<?php

namespace App\Filament\Resources\ValueEntries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ValueEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entry Information')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('entry_no'),
                            TextEntry::make('item_ledger_entry_no')->label('Item Ledger Entry No'),
                            TextEntry::make('item_ledger_entry_type')->badge(),
                            TextEntry::make('entry_type')->badge(),
                        ]),
                    ]),

                Section::make('Item & Location')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('item.item_code')->label('Item No'),
                            TextEntry::make('item.description')->label('Item Description')->columnSpan(2),
                            TextEntry::make('variant_code'),
                            TextEntry::make('location.code')->label('Location Code'),
                            TextEntry::make('bin_code'),
                            TextEntry::make('serial_no'),
                            TextEntry::make('lot_no'),
                            TextEntry::make('expiration_date')->date(),
                        ]),
                    ]),

                Section::make('Source & Document')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('source_type')->badge(),
                            TextEntry::make('source_no'),
                            TextEntry::make('source_line_no'),
                            TextEntry::make('source_batch_name'),
                            TextEntry::make('document_type')->badge(),
                            TextEntry::make('document_no'),
                            TextEntry::make('document_line_no'),
                            TextEntry::make('posting_date')->date(),
                            TextEntry::make('valuation_date')->date(),
                            TextEntry::make('description')->columnSpan(4),
                        ]),
                    ]),

                Section::make('Quantities & Costing')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('quantity'),
                            TextEntry::make('invoiced_quantity'),
                            TextEntry::make('costing_method')->badge(),
                        ]),
                    ]),

                Section::make('Cost Amounts')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('cost_amount_actual')->money('NGN')->label('Actual (LCY)'),
                            TextEntry::make('cost_amount_actual_acy')->money('NGN')->label('Actual (ACY)'),
                            TextEntry::make('cost_amount_expected')->money('NGN')->label('Expected (LCY)'),
                            TextEntry::make('cost_amount_expected_acy')->money('NGN')->label('Expected (ACY)'),
                            TextEntry::make('direct_cost_amount')->money('NGN'),
                            TextEntry::make('indirect_cost_amount')->money('NGN'),
                            TextEntry::make('overhead_amount')->money('NGN'),
                            TextEntry::make('unit_cost')->money('NGN'),
                            TextEntry::make('unit_cost_acy')->money('NGN')->label('Unit Cost (ACY)'),
                            TextEntry::make('rollover_amount')->money('NGN'),
                        ]),
                    ]),

                Section::make('Single Level Cost Breakdown')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('single_level_material_cost')->money('NGN')->label('Material'),
                            TextEntry::make('single_level_capacity_cost')->money('NGN')->label('Capacity'),
                            TextEntry::make('single_level_subcontracted_cost')->money('NGN')->label('Subcontracted'),
                            TextEntry::make('single_level_overhead_cost')->money('NGN')->label('Overhead'),
                            TextEntry::make('single_level_mfg_ovhd_cost')->money('NGN')->label('Mfg Overhead'),
                        ]),
                    ]),

                Section::make('Variance Analysis')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('variance_amount')->money('NGN')->label('Total Variance'),
                            TextEntry::make('purchase_variance_amount')->money('NGN')->label('Purchase'),
                            TextEntry::make('material_variance_amount')->money('NGN')->label('Material'),
                            TextEntry::make('capacity_variance_amount')->money('NGN')->label('Capacity'),
                            TextEntry::make('capacity_overhead_variance_amount')->money('NGN')->label('Capacity Ovh'),
                            TextEntry::make('manufacturing_overhead_variance_amount')->money('NGN')->label('Mfg Ovh'),
                        ]),
                    ]),

                Section::make('Capacity & Routing')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('capacity_type')->badge(),
                            TextEntry::make('capacity_no'),
                            TextEntry::make('workCenter.code')->label('Work Center'),
                            TextEntry::make('machineCenter.code')->label('Machine Center'),
                            TextEntry::make('routing_no'),
                            TextEntry::make('routing_reference_no'),
                            TextEntry::make('operation_no'),
                        ]),
                    ]),

                Section::make('Work Center Purchase Costs')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('work_center_purch_capacity')->money('NGN')->label('Purch Capacity'),
                            TextEntry::make('work_center_purch_oh_capacity')->money('NGN')->label('Purch OH Capacity'),
                            TextEntry::make('work_center_purch_direct_cost')->money('NGN')->label('Purch Direct Cost'),
                            TextEntry::make('work_center_purch_ovhd_cost')->money('NGN')->label('Purch OH Cost'),
                        ]),
                    ]),

                Section::make('Order References')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('production_order_no')->label('Production Order'),
                            TextEntry::make('production_order_line_no')->label('Prod. Order Line'),
                            TextEntry::make('production_order_component_line_no')->label('Component Line'),
                            TextEntry::make('prod_order_line_item_no')->label('Prod. Order Item'),
                            TextEntry::make('purchase_order_no')->label('Purchase Order'),
                            TextEntry::make('purchase_order_line_no')->label('PO Line'),
                            TextEntry::make('sales_order_no')->label('Sales Order'),
                            TextEntry::make('sales_order_line_no')->label('SO Line'),
                        ]),
                    ]),

                Section::make('Vendor & Customer')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('vendor_no'),
                            TextEntry::make('customer_no'),
                        ]),
                    ]),

                Section::make('General Ledger Posting')
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('gl_posted')->boolean()->label('G/L Posted'),
                            TextEntry::make('gl_posting_date')->date()->label('G/L Posting Date'),
                            TextEntry::make('gl_entry_no')->label('G/L Entry No'),
                            TextEntry::make('gl_account_no')->label('G/L Account'),
                            TextEntry::make('chartOfAccount.account_name')->label('G/L Account Name'),
                            TextEntry::make('balancing_account_no')->label('Balancing Account'),
                            TextEntry::make('balancingChartOfAccount.account_name')->label('Balancing Acct Name'),
                        ]),
                    ]),

                Section::make('Cost Adjustment')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('cost_adjusted')->boolean(),
                            TextEntry::make('cost_adjustment_date')->date(),
                            TextEntry::make('cost_adjustment_entry_no'),
                            IconEntry::make('cost_is_adjusted')->boolean()->label('Cost Is Adjusted'),
                            IconEntry::make('cost_is_changed_by_user')->boolean()->label('Changed By User'),
                            TextEntry::make('reason_code'),
                        ]),
                    ]),

                Section::make('Dimensions')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('global_dimension_1_code')->label('Global Dimension 1'),
                            TextEntry::make('global_dimension_2_code')->label('Global Dimension 2'),
                            TextEntry::make('shortcut_dimension_codes')
                                ->label('Shortcut Dimensions')
                                ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                            TextEntry::make('dimension_set_id')
                                ->label('Dimension Set ID')
                                ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                        ]),
                    ]),

                Section::make('Other Details')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('completely_invoiced')->boolean(),
                            IconEntry::make('last_invoice')->boolean(),
                            IconEntry::make('expected_cost')->boolean(),
                            IconEntry::make('partial_posted')->boolean(),
                            TextEntry::make('user_id'),
                            TextEntry::make('source_code'),
                            TextEntry::make('adjustment_entry_no'),
                            TextEntry::make('original_entry_no'),
                            TextEntry::make('original_document_no'),
                            TextEntry::make('original_posting_date')->date(),
                        ]),
                    ]),

                Section::make('Job & Warehouse')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('job_no'),
                            TextEntry::make('job_task_no'),
                            TextEntry::make('job_line_type')->badge(),
                            TextEntry::make('warehouse_activity_no'),
                            TextEntry::make('warehouse_line_no'),
                            TextEntry::make('registering_no'),
                        ]),
                    ]),
            ]);
    }
}
