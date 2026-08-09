# Manufacturing Phase 2A Completion

Phase 2A is complete at the boundary of multi-level production planning and inventory-mediated execution. It does not introduce a separate posting engine.

## Tested 3-Level Scenario

The completion regression covers this hierarchy:

1. `FG-HERBAL-CARTON`
2. `SFG-BOTTLED-HERBAL`
3. `SFG-BULK-HERBAL`
4. Raw material inputs:
   - Ginseng
   - Yohimbine
   - Sodium Benzoate
   - Ficus Carica
   - Sodium Saccharine
5. Packaging inputs:
   - Bottle
   - Cap
   - Label
   - Shrink Sleeve
   - Paper Tray
   - Carton

The test uses production-style decimal quantities such as `17.065728`, `39.710304`, and `289.57527360`.

## Execution Flow

The tested runtime flow is:

1. Create a root production order.
2. Plan the multi-level hierarchy.
3. Generate child production orders.
4. Release the lowest-level child.
5. Consume raw materials through normal production consumption.
6. Post child output through normal production output.
7. Synchronize supply fulfilment from child output ledger totals.
8. Release the parent child.
9. Consume the supplied semi-finished item through normal production consumption.
10. Post parent output.
11. Propagate supply upward.
12. Release the root.
13. Consume the supplied intermediate item.
14. Post root output.
15. Finish all hierarchy members.
16. Synchronize hierarchy progress.
17. Verify reconciliation remains clean.

## Cost Flow

Cost travels only through existing inventory and costing layers:

- Raw material consumption creates outbound `ItemLedgerEntry` records, `ItemApplicationEntry` records, `ValueEntry` records, and G/L posting.
- Child output receives accumulated child WIP cost through `ProductionOutputCostService`.
- Parent consumption applies the child output inventory layer through `ItemApplicationService`.
- Parent WIP receives the child output inventory cost as its own material cost.
- Root output receives accumulated root WIP cost.

The parent does not receive child raw-material Value Entries directly. Child raw-material cost is represented in the parent only through the semi-finished item inventory layer.

## WIP Separation

Each production order owns its own WIP:

- Child order: raw material and child output cost.
- Parent order: semi-finished child item consumption, packaging consumption, and parent output cost.
- Root order: direct intermediate consumption, final packaging consumption, and root output cost.

Value Entries remain attributable to the production order that posted the source ledger entry.

## Item Application

Phase 2A uses the existing item application engine. The completion test proves the parent consumption outbound layer is applied to the generated child output inbound layer when that layer is the available FIFO layer.

Phase 2A does not add a new exact-application or genealogy engine. Deeper genealogy and operation-level exact hand-off remain Phase 2B or later.

## Partial Execution

Partial output and partial consumption are supported.

Example:

- Required child supply: `2.00000000`
- First output: `0.40000000`
- Second output: `0.25000000`
- Cumulative supplied: `0.65000000`
- First parent consumption: `0.30000000`
- Second parent consumption: `0.20000000`
- Cumulative consumed: `0.50000000`

Repeated supply and reservation syncs are idempotent and do not duplicate fulfilment or consumption.

## Overproduction And Underproduction

Overproduction is valid inventory:

- Child output can exceed parent demand.
- Supply fulfilment is capped at parent demand.
- Excess output remains ordinary inventory.

Underproduction is not auto-corrected:

- Child finished short leaves the supply link short.
- Parent finish/readiness remains blocked.
- Reconciliation reports the shortage.

## Replanning And Cancellation

Destructive replanning is allowed only before operational activity.

It is rejected after:

- child consumption;
- child output;
- parent consumption;
- capacity posting;
- irreversible supply/reservation consumption.

Phase 2A does not automatically reverse ledger history during cancellation. Existing reversal/correction workflows remain authoritative.

## Reconciliation

`php artisan biwms:manufacturing-cost-reconcile --details` reports Phase 2A runtime issues including:

- child output supply mismatch;
- supply greater than parent demand;
- child finished with supply shortage;
- reservation availability mismatch;
- reservation consumption greater than available supply;
- cancelled supply link with active reservation;
- parent finished with unresolved child demand;
- parent consumption greater than supplied quantity;
- hierarchy status mismatch;
- orphan supply link;
- orphan reservation;
- duplicate active reservation;
- child output consumed before child output cost is available.

The command remains report-only.

## Late Cost Propagation

Phase 2A does not introduce a cross-level late-cost propagation engine.

Existing cost-adjustment services can identify and post controlled adjustment entries, but a dedicated multi-level late-cost propagation workflow from child output through already-consumed parent output is intentionally deferred. This is a Phase 2B or later candidate.

## Non-Goals

Phase 2A does not implement:

- inter-order routing dependencies;
- cross-order operation synchronization;
- advanced genealogy graph;
- lot/serial genealogy beyond existing ledger traceability;
- intermediate operation handoff;
- finite capacity scheduling;
- campaign planning;
- alternate BOM/routing selection;
- Phase 2B;
- Phase 2C.

## Phase 2A Completion Status

Phase 2A is complete when the focused completion tests, manufacturing feature tests, inventory/accounting/Filament/Sales regression tests, full suite, and manufacturing/shop-floor/inventory/security/health commands pass.
