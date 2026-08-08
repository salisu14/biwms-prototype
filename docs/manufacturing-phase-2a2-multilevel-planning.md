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
