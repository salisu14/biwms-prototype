# Phase 1D Manufacturing Costing

Phase 1D stabilizes single-level production costing without changing the Phase 1A-1C ownership boundaries.

## Ownership Boundaries

- `ItemLedgerEntry` owns inventory quantity movement for production consumption and output.
- `CapacityLedgerEntry` owns work-center and machine-center capacity usage.
- `ValueEntry` owns inventory value and WIP value movements.
- `ValueEntryAccountingOrchestrator` posts inventory/WIP/capacity/variance G/L from Value Entries.
- Source manufacturing services orchestrate posting, status changes, and audit context. They do not directly write duplicate inventory value G/L.
- The posting kernel remains the balanced G/L writer.

The only permitted manufacturing inventory-accounting G/L path is:

```text
source posting service -> Item/Capacity Ledger Entry -> Value Entry -> ValueEntryAccountingOrchestrator -> Posting Kernel
```

Generic journal infrastructure may still call the posting kernel directly for non-inventory journal flows. Manufacturing WIP, output, capacity application, and variance G/L must originate from `ValueEntry`.

## Cost Components

New manufacturing value postings use explicit cost components:

- `direct_material`
- `direct_capacity`
- `capacity_overhead`
- `material_overhead`
- `subcontracting`
- `output`
- `standard_cost_variance`
- `material_price_variance`
- `material_quantity_variance`
- `capacity_rate_variance`
- `capacity_efficiency_variance`
- `capacity_overhead_variance`
- `output_quantity_variance`
- `rounding_variance`
- `cost_adjustment`

Legacy component names such as `material`, `capacity`, and `overhead` are still recognized by summary/reconciliation logic for existing data.

Unknown manufacturing cost-component strings are not silently mapped to a normal posting category. They are rejected before G/L posting or reported by reconciliation as `unsupported_manufacturing_cost_component`.

## Output Allocation States

Production output cost allocations are append-friendly records with explicit states:

- `pending`
- `provisional`
- `final`
- `reversed`

Allowed transitions are:

- `pending` -> `provisional`
- `pending` -> `final`
- `provisional` -> `final`
- `provisional` -> `reversed`
- `final` -> `reversed`

Invalid silent transitions, such as `final` -> `provisional` or `reversed` -> `final`, are blocked. A provisional zero-cost allocation can later be finalized when actual cost arrives. A final zero-cost allocation is valid when there is genuinely no eligible accumulated cost.

## Cost Settlement Status

Production order cost settlement is tracked separately from operational production status:

- `not_ready`
- `pending`
- `settled`
- `adjustment_required`

A production order may be operationally finished while its costing status is still `pending` or `not_ready`. Late costs after settlement should move the order to `adjustment_required`; a later append-only adjustment run may return it to `settled`.

Stable settlement classifications include:

- `ready`
- `unallocated_cost`
- `pending_expected_cost`
- `pending_actual_material_cost`
- `pending_capacity_cost`
- `pending_overhead_cost`
- `rounding_residual`
- `true_production_variance`
- `late_cost_adjustment_required`
- `posting_setup_missing`
- `costing_period_closed`
- `required_output_not_posted`
- `required_consumption_not_posted`
- `required_capacity_not_posted`
- `unresolved_production_journal_lines`
- `production_order_not_operationally_finished`

Only `true_production_variance` creates a production variance `ValueEntry`. Pending-cost classifications do not post variance.

## Posting Flow

Production consumption creates negative Item Ledger Entries and material Value Entries. Cost comes from item application where available and from current item cost only as a fallback.

Capacity posting creates Capacity Ledger Entries, then direct-capacity and overhead Value Entries. Those Value Entries post WIP debits and applied-cost credits through the value-entry G/L orchestrator.

Production journal capacity posting uses the journal line as its durable source identity. Retrying the same posted journal line reuses the existing capacity ledger entry, value entries, and G/L posting; it does not advance routing progress a second time. A different journal line for the same routing operation remains allowed.

Production output creates positive Item Ledger Entries and output Value Entries. Output cost allocation draws from accumulated material, capacity, and overhead costs. Early zero-cost output allocations can be recalculated during settlement after costs arrive.

Finish/cost settlement allocates remaining eligible production cost to output and records residual variance as a variance Value Entry. Variance G/L is posted from the Value Entry, not by a direct source-service G/L write.

## Reconciliation

Run:

```bash
php artisan biwms:manufacturing-cost-reconcile --details
```

Optional filters and export:

```bash
php artisan biwms:manufacturing-cost-reconcile --production-order=PO-001 --details
php artisan biwms:manufacturing-cost-reconcile --details --export=storage/app/reports/manufacturing-cost-reconcile.json
```

The command is report-only. It does not repair production, inventory, value, or G/L data.

It reports:

- production consumption/output Item Ledger Entries missing Value Entries;
- capacity entries missing Value Entries;
- manufacturing Value Entries with value that have not posted to G/L;
- unsupported manufacturing cost components;
- duplicate capacity postings;
- duplicate output allocations;
- output cost over-allocation;
- finished orders without cost settlement;
- settled orders with unexplained open WIP.

## Current Limitations

Phase 1D is intentionally single-level. It does not implement multi-level BOM rollup, semi-finished subassembly costing, co-products, by-products, finite scheduling, or full standard-cost worksheet/versioning. Those remain future phases.
