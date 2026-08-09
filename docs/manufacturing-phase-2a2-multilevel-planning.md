# Manufacturing Phase 2A.2 Multi-Level Planning

Phase 2A.2 adds a planning runtime on top of the Phase 2A.1 hierarchy schema. It does not change production posting, item applications, value entries, capacity entries, or G/L posting.

## Runtime Flow

1. `MultiLevelBomExplosionService` recursively explodes certified production BOMs with cycle and max-depth protection.
2. `ProductionHierarchyService` writes the root hierarchy and source-specific hierarchy nodes with deterministic idempotency keys.
3. `ChildProductionOrderGenerationService` synchronizes parent production order components and generates child production orders for manufactured/semi-finished requirements.
4. `ProductionMaterialReservationService` creates child-output supply links and reservations from the child order back to the parent component demand.
5. `MultiLevelProductionPlanningService` orchestrates the full flow from the Production Order action.

## Ownership Boundary

The multi-level planner owns only planning records:

- `production_hierarchies`
- `production_hierarchy_nodes`
- generated child `production_orders`
- `production_order_components` created from hierarchy demand
- `production_order_supply_links`
- `production_material_reservations`

It does not consume inventory, post output, create `ItemLedgerEntry`, create `ValueEntry`, create `CapacityLedgerEntry`, or post G/L. Those remain owned by the existing production posting and value-entry services.

## Item Classification

Manufactured requirements are detected from item type and certified BOM availability:

- `SEMI_FINISHED` or `FINISHED_GOOD` with a certified BOM: generated child order.
- raw, packaging, consumable, spare part, inventory, or service items: direct component demand.

Names/descriptions are never used to infer manufacturing behavior.

## Idempotency

Planning can be safely retried while the root order remains simulated/planned/firm planned and has no ledger activity. Hierarchy nodes, child orders, supply links, and reservations are matched by deterministic source identity instead of duplicated.

## Reconciliation

`php artisan biwms:manufacturing-cost-reconcile --details` now reports Phase 2A.2 planning gaps, including:

- manufactured hierarchy nodes without generated child orders;
- generated child orders without supply links;
- manufactured component demands without child-output reservations.

These checks are diagnostic only and do not repair data.

## Phase 2A.3 Execution Lifecycle

The executable lifecycle is inventory-mediated:

1. Release and execute generated child production orders with the existing production order workflow.
2. Child output posts finished/semi-finished inventory through normal Item Ledger, Value Entry, and G/L posting.
3. `ProductionSupplyFulfilmentService` derives supplied quantity from child output ledger totals and caps fulfilment at parent demand.
4. Parent production consumes the semi-finished item through normal production consumption.
5. `ProductionReservationConsumptionService` validates available child supply and derives reservation consumed/remaining quantities from the parent component.
6. `ProductionHierarchyProgressService` synchronizes hierarchy and node progress from orders, supply links, and reservations.

No child output is moved directly into parent WIP. Cost flows through inventory:

- Child: raw material and capacity cost -> child WIP -> semi-finished inventory.
- Parent: semi-finished inventory -> parent WIP -> finished goods inventory.

This keeps WIP and Value Entries attributable to their own production orders.

## Partial Supply And Consumption

Partial output and partial consumption are allowed.

Example:

- Parent demand: `100 kg`.
- Child output: `60 kg`.
- Parent consumes: `40 kg`.
- Supply link: required `100`, supplied `60`, consumed `40`, remaining available `20`.
- Reservation: quantity `100`, remaining `60`, status `partially_consumed`.

Later child output continues fulfilment without duplicating prior supply because fulfilment is recalculated from child output ledger totals.

## Overproduction And Underproduction

If child output exceeds parent demand, the supply link is capped at demand and the excess remains ordinary inventory.

If the child finishes short, the supply link remains short and reconciliation reports the underproduction. Parent finishing remains blocked until manufactured demand is resolved or corrected through a controlled planning/business workflow.

## Replanning And Cancellation

Destructive hierarchy replanning is allowed only before operational activity. Replanning is blocked after any root/child member has ledger activity or irreversible reservation fulfilment/consumption.

Cancelling planning reservations releases commitment only. It does not delete or rewrite item ledger history.

## Reconciliation

`php artisan biwms:manufacturing-cost-reconcile --details` includes Phase 2A.3 diagnostics for:

- child output posted but supply not synchronized;
- supplied quantity exceeding parent demand;
- child finished with supply shortage;
- reservation availability mismatch;
- reservation consumption exceeding available supply;
- cancelled supply links with active reservations;
- parent finished with unresolved manufactured demand;
- parent consumption exceeding supplied child output;
- hierarchy status mismatch;
- orphan supply links or reservations;
- duplicate active reservations;
- child output consumed before child output cost is available.

The command remains report-only.

## Limitation

Hierarchy-aware exact item application to a generated child output layer is not forced in Phase 2A.3. Parent consumption uses the existing item application architecture. When the generated child output is the available inbound layer, normal FIFO/specific costing provides the expected trace. Deeper genealogy and operation-level exact hand-off are reserved for Phase 2B.
