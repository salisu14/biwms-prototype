# Manufacturing Phase 2B: Routing Dependencies and Genealogy Foundation

## Scope

Phase 2B adds cross-order routing dependency coordination on top of the Phase 2A hierarchy model. It does not create a new posting engine and does not move inventory or WIP directly.

Implemented:

- `ProductionOperationDependency` records for parent/child production order operation sequencing.
- `ProductionIntermediateHandoff` records for lightweight intermediate supply visibility.
- Dependency generation from generated child-order supply links.
- Cycle detection for operation dependency graphs.
- Shop-floor start blocking when upstream output, quality release, or required supply is not ready.
- Dependency progress synchronization when child supply links or parent reservation consumption change.
- Query-time production genealogy tracing from `ItemLedgerEntry` and `ItemApplicationEntry`.
- Report-only reconciliation through `php artisan biwms:manufacturing-hierarchy-reconcile --details`.
- Minimal Production Order and Production Operation Execution UI visibility.
- Explicit policies and permissions for dependency and handoff visibility/generation.

## Posting Boundary

Phase 2B respects the existing accounting ownership boundary:

- Inventory quantity remains owned by `ItemLedgerEntry`.
- Inventory application remains owned by `ItemApplicationEntry`.
- Inventory value remains owned by `ValueEntry`.
- Capacity remains owned by `CapacityLedgerEntry`.
- Inventory-related G/L remains owned by the Value Entry accounting orchestrator.
- Accounting posting remains owned by the posting kernel.

`ProductionOperationDependency` and `ProductionIntermediateHandoff` are coordination and diagnostic records only. They never post inventory, value, capacity, or G/L entries.

## Dependency Flow

1. Phase 2A creates a production hierarchy, child production orders, supply links, and child-output reservations.
2. Phase 2B generates operation dependencies from generated child-order supply links.
3. The child order's final routing line becomes the upstream operation.
4. The parent component's `routing_link_code` maps to the downstream parent operation when available.
5. If no component operation mapping exists, the parent order's first routing line is used and the limitation is recorded in dependency metadata.
6. Shop-floor start checks the downstream routing line dependency readiness.
7. Child output supply and parent consumption synchronize dependency and handoff progress.

## Readiness Rules

An operation can start only when all active downstream dependencies are satisfied.

Blocking conditions include:

- missing upstream or downstream order/operation references;
- cancelled upstream production order;
- upstream operation not complete for finish-to-start dependencies;
- no upstream output or reserved supply available;
- available supply below the minimum start quantity;
- active upstream quality hold.

Fulfilled dependencies are still evaluated for readiness so a later quality hold can block downstream execution until resolved.

## Genealogy

The genealogy foundation is intentionally query-time:

- backward trace starts from a finished output `ItemLedgerEntry`;
- consumption inputs are read from the production order's consumption ledger entries;
- source layers are read from `ItemApplicationEntry`;
- child production output is followed recursively when the applied inbound entry belongs to another production order;
- forward trace starts from an inbound layer and follows outbound applications to production outputs.

No separate QR, lot genealogy graph, or permanent genealogy edge table is introduced in Phase 2B.

## Reconciliation

Run:

```bash
php artisan biwms:manufacturing-hierarchy-reconcile --details
```

The command is report-only and checks:

- dependencies with missing upstream/downstream operations;
- dependency cycles;
- duplicate active dependencies;
- fulfilled dependencies that no longer pass readiness;
- blocked dependencies that are now ready;
- downstream operations started before dependencies were satisfied;
- child output available while dependency status was not updated;
- handoff quantity mismatches;
- missing ledger references for genealogy;
- cancelled upstream orders with ready dependencies;
- completed downstream operations with unresolved dependencies.

## Current Limitations

- Minimum start quantity currently defaults to the full required quantity. Partial-start governance is represented by fields but not expanded into Phase 2C scheduling rules.
- Dependency generation uses `routing_link_code` where present and otherwise falls back to the parent first routing line.
- Handoffs are visibility and reconciliation records, not warehouse movement documents.
- Genealogy is read from item application and ledger records at query time.
- No automatic historical backfill is performed.

## Phase 2C

Phase 2C adds APS Lite scheduling, finite-capacity planning, alternate resource selection, campaigns, and production schedule reconciliation.

See:

- `docs/manufacturing-phase-2c.md`
- `docs/manufacturing-phase-2c-architecture.md`

The following remain deferred beyond Phase 2C:

- alternate routing dependency optimization;
- durable genealogy graph materialization;
- advanced WIP transfer orchestration;
- automated quality-driven rework routing.
