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

## Expected Manufacturing Cost

`ExpectedManufacturingCostService` calculates a deterministic `ProductionExpectedCostSnapshot` for each production order and output quantity. The snapshot stores the production order, finished item, BOM/routing references, costing date, component expected quantities and costs, routing expected times and rates, overhead, actor, timestamp, and calculation identity.

Expected production cost is:

```text
expected direct material
+ expected direct capacity
+ expected capacity overhead
= expected production cost
```

Material quantity uses `production_order_components.expected_quantity_base` prorated by output quantity. If the exploded expected quantity is unavailable, the fallback is:

```text
quantity_per * output_quantity_base * (1 + scrap_percent)
```

Material expected unit cost source:

- `STANDARD`: item standard cost, falling back to component/order unit cost if absent.
- `FIFO`, `LIFO`, `AVERAGE`, `SPECIFIC`: component unit cost, then item unit cost, then last direct cost.

Capacity expected cost uses routing setup time plus prorated run time. Queue and wait time are not cost-bearing in Phase 1D. Direct capacity and overhead amounts use routing-line rates where present, otherwise the machine/work-center rates.

Expected Value Entries use explicit components:

- `expected_direct_material`
- `expected_direct_capacity`
- `expected_capacity_overhead`
- `expected_output`

Expected G/L posting uses the existing `accounts.post_expected_inventory_cost_to_gl` flag and still flows through `ValueEntryAccountingOrchestrator`.

## Expected-Cost Clearing

Manufacturing expected-cost clearing follows the Phase 1C append-only pattern:

```text
original expected Value Entry
+ expected-cost clearing Value Entry
+ actual Value Entry
```

The original expected Value Entry is never overwritten or deleted. Clearing entries reference the original expected entry, the actual source entry, production order, clearing quantity, clearing amount, posting date, original source date, actor, and idempotency key.

## Variance Calculation

`ProductionVarianceCalculationService` calculates structured variance rows without writing ledgers. `ProductionVarianceValueEntryService` posts eligible variance rows as Value Entries, then the orchestrator posts G/L.

Formulas:

```text
material price variance =
actual quantity consumed * (actual unit cost - expected unit cost)

material quantity variance =
(actual quantity consumed - expected quantity allowed) * expected unit cost

capacity rate variance =
actual capacity time * (actual capacity rate - expected capacity rate)

capacity efficiency variance =
(actual capacity time - expected time allowed) * expected capacity rate

capacity overhead variance =
actual overhead - expected overhead allowed

standard cost variance =
actual accumulated manufacturing cost - standard output inventory cost
```

Expected quantity/time allowed is based on actual good output, not blindly on planned output. Differences below `DecimalTolerance::AMOUNT` are ignored for operational variance and may be treated as rounding.

## Standard-Cost Production

For standard-cost finished goods, output inventory remains valued at standard cost. Actual material, capacity, and overhead remain separate manufacturing Value Entries. Settlement posts the difference as explicit production variance; late costs adjust variance and settlement state instead of rewriting the original standard output cost.

## Late Cost Propagation

Late material-cost propagation extends the existing bounded `CostAdjustmentService`:

```text
inbound material cost change
-> ItemApplicationEntry traversal
-> consumption cost adjustment
-> production adjustment Value Entry
-> output/downstream adjustment marker
```

Only exact item-application links are followed. Broad matching by item/date/order is intentionally avoided. If a settled production order receives late cost, its costing status moves from `settled` to `adjustment_required` with `late_cost_adjustment_required` classification.

Late capacity-cost propagation is append-only by reversal/replacement of capacity entries and related Value Entries. Phase 1D does not mutate posted Capacity Ledger Entries.

## Costing Periods

The existing `CostingPeriodService` guards expected-cost calculation, settlement, variance posting, and cost adjustment. Closed periods require a configured allowed adjustment posting date; the original economic date remains on the source record while the adjustment posting date is stored on the adjustment Value Entry.

## Reversals

Posted manufacturing cost corrections are append-only:

- expected cost reversal references the expected entry;
- clearing reversal references the clearing entry;
- allocation reversal marks the original allocation as reversed and creates replacement records;
- variance reversal references the variance Value Entry;
- reversed allocations cannot transition back to provisional or final.

## Production Costing UI

The Production Order view exposes a guarded costing section with expected, actual, variance, output allocation, uncleared expected cost, adjustment, settlement status, and settlement classification fields. Header actions call domain services:

- Calculate Expected Cost
- Settle Cost
- Cost Reconcile

Settlement is protected by the existing sensitive-action password confirmation helper.

## Permissions

Phase 1D special permissions:

- `manufacturing.production_cost.view`
- `manufacturing.production_cost.view_details`
- `manufacturing.production_cost.calculate_expected`
- `manufacturing.production_cost.settle`
- `manufacturing.production_cost.adjust`
- `manufacturing.production_cost.reverse`
- `manufacturing.production_cost.reconcile`
- `manufacturing.production_variance.view`
- `manufacturing.production_variance.post`
- `manufacturing.production_variance.reverse`
- `manufacturing.expected_cost.view`
- `manufacturing.expected_cost.calculate`
- `manufacturing.expected_cost.clear`
- `manufacturing.capacity_cost.view`
- `manufacturing.capacity_cost.adjust`

Ledger-history resources remain read-only through existing policy/resource controls.

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

Additional Phase 1D classifications include expected-cost missing/uncleared, actual material/capacity/output cost missing, variance mismatch, late adjustment pending, duplicate expected/clearing/variance/adjustment entries, missing manufacturing posting account, unsupported variance type, broken reversal chain, settlement history mismatch, production summary mismatch, and manufacturing G/L without Value Entry ownership.

## Numerical Example

```text
Planned output: 1,000 bottles

Expected material: NGN 500,000
Expected capacity: NGN 80,000
Expected overhead: NGN 20,000
Expected production cost: NGN 600,000

Actual material: NGN 520,000
Actual capacity: NGN 76,000
Actual overhead: NGN 22,000
Actual production cost: NGN 618,000
```

If expected material quantity allowed is 10,000 units at NGN 50 and actual consumption is 10,200 units at NGN 50.980392:

```text
Material price variance = 10,200 * (50.980392 - 50.000000) = NGN 10,000
Material quantity variance = (10,200 - 10,000) * 50 = NGN 10,000
Capacity total variance = 76,000 - 80,000 = NGN -4,000
Overhead variance = 22,000 - 20,000 = NGN 2,000
Total variance = 10,000 + 10,000 - 4,000 + 2,000 = NGN 18,000
```

This reconciles expected production cost of NGN 600,000 to actual production cost of NGN 618,000.

## PostgreSQL Note

The local `migrate:fresh --env=testing` check can fail before running Phase 1D SQL when PostgreSQL drops the very large testing schema:

```text
SQLSTATE[53200]: out of shared memory
You might need to increase max_locks_per_transaction
```

That is an infrastructure limit on schema teardown, not a generated-column or Phase 1D SQL issue. Do not change PostgreSQL settings automatically from the application.

## Current Limitations

Phase 1D is intentionally single-level. It does not implement multi-level BOM rollup, semi-finished subassembly costing, co-products, by-products, finite scheduling, or full standard-cost worksheet/versioning. Those remain future phases.

Phase 2 exclusions also include advanced shop-floor control, finite-capacity dispatching, drag-and-drop cost templates, and parent-child production-order cost rollup semantics.
