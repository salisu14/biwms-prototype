# Manufacturing Phase 2A Implementation Log

## Phase 2A.1: Schema, Enums, Models, and Backward-Compatible Foundation

Implemented:

- Added `SEMI_FINISHED` to the item type enum.
- Added hierarchy provenance fields to production orders.
- Added hierarchy traceability fields to production order components.
- Added production hierarchy header and node tables.
- Added production order supply link table.
- Added production material reservation table.
- Added enums, models, relationships, factories, policies, and permissions for the new foundation records.
- Added tests for schema, relationships, immutability guards, authorization, and architecture boundaries.

Scope intentionally not implemented in this checkpoint:

- BOM explosion behavior.
- Child production order generation.
- Stock availability or material reservation runtime.
- Parent consumption from child output.
- Cost propagation, WIP, settlement, Value Entry, Item Ledger Entry, Capacity Ledger Entry, or G/L posting behavior.
- Filament resources and user workflow.

The ownership boundary remains:

- Inventory quantity is owned by Item Ledger Entries.
- Item application is owned by Item Application Entries.
- Capacity is owned by Capacity Ledger Entries.
- Inventory value is owned by Value Entries.
- Inventory-related G/L posting is orchestrated from Value Entries.
- Accounting posting remains owned by the posting kernel.

## Phase 2A.2: Multi-Level Planning

Implemented:

- Recursive certified-BOM explosion with cycle and max-depth protection.
- Production hierarchy headers and nodes for explicit demand structure.
- Generated child production orders for manufactured and semi-finished component demand.
- Supply links from parent component demand to generated child orders.
- Child-output reservations for generated supply.
- Idempotent planning retries while the hierarchy has no operational activity.
- `Plan Multi-Level` action on Production Orders.
- Report-only reconciliation checks for missing child orders, links, and reservations.

## Phase 2A.3: Execution Integration

Implemented:

- Child production orders execute through the existing Phase 1 production workflow.
- Child output remains normal inventory output: `ItemLedgerEntry`, `ValueEntry`, and G/L are still owned by existing posting services.
- Child output fulfilment synchronizes generated supply links from posted child output totals.
- Parent manufactured-component consumption remains normal production consumption through inventory and item application.
- Reservation consumption is derived from parent component actual consumption and is retry-safe.
- Parent finishing is blocked when manufactured child demand is unresolved.
- Hierarchy progress is synchronized from related order, supply, and reservation state.
- Replanning is blocked after root/child ledger activity or irreversible reservation fulfilment.
- Reconciliation detects Phase 2A.3 execution gaps, including supply/output mismatches, over-supply, underproduction, reservation mismatches, and unresolved parent demand.
- Production Order views show concise hierarchy execution status and blocking reason.

## Phase 2A Complete / Remaining

Phase 2A is complete at the boundary of multi-level BOM/order hierarchy plus inventory-mediated child-to-parent execution.

The completion pass proves:

- a three-level herbal hierarchy executes end to end;
- raw material cost flows into child output inventory;
- child output inventory cost flows into parent WIP through item application;
- parent/root WIP remains separated from child raw-material Value Entries;
- partial supply and partial consumption remain cumulative and idempotent;
- overproduction is capped for parent fulfilment while excess remains inventory;
- underproduction blocks parent finish and is reported diagnostically;
- unsafe replanning after ledger/reservation activity is rejected;
- Phase 2A services do not bypass Value Entry or G/L posting ownership.

Still intentionally deferred to Phase 2B or later:

- routing-to-routing dependencies between orders;
- operation-level predecessor/successor hand-offs;
- cross-order operation synchronization;
- advanced lot genealogy and production genealogy graph;
- alternate routing dependency logic;
- advanced WIP transfer orchestration.

See `docs/manufacturing-phase-2a-completion.md` for the completion scenario, cost-flow assertions, reconciliation coverage, and known limitations.

## Phase 2B

Phase 2B adds inter-order routing dependencies, intermediate handoff visibility, shop-floor readiness blocking, and query-time genealogy tracing.

See:

- `docs/manufacturing-phase-2b.md`
- `docs/manufacturing-phase-2b-architecture.md`
