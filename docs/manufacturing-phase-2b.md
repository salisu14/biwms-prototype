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
5. If no component operation mapping exists, generation may infer the downstream operation only when the parent order has exactly one routing line.
6. If the parent has multiple possible downstream operations, generation stops with `Dependency mapping requires review` and does not create a misleading dependency.
7. Shop-floor start checks the downstream routing line dependency readiness.
8. Child output supply and parent consumption synchronize dependency and handoff progress.

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
- unresolved dependency mappings;
- fulfilled dependencies with insufficient upstream supply;
- quality-blocked dependencies marked ready;
- downstream operations completed before dependencies were satisfied;
- orphan dependencies and handoffs;
- dependency status drift.

## Current Limitations

- Minimum start quantity currently defaults to the full required quantity. The field supports controlled partial starts when explicitly lowered by approved planning rules.
- Dependency generation uses `routing_link_code` where present and otherwise allows only single-operation inference. Ambiguous parent routing must be corrected by assigning the component to an operation.
- Handoffs are visibility and reconciliation records, not warehouse movement documents.
- Genealogy is read from item application and ledger records at query time.
- No automatic historical backfill is performed.

## Phase 2B Completion Status

The hardening pass makes dependency generation safe for multi-section production. Bulk/intermediate output can no longer silently feed the first parent operation when the intended downstream operation is ambiguous.

Partial handoffs are cumulative and idempotent. Available and consumed handoff quantities are capped at the dependency required quantity while the underlying supply link can still retain the physical produced quantity for diagnostics.

Cancellation and quality holds remain execution gates. Invalid or upstream-cancelled dependencies continue to block downstream readiness until the dependency is cancelled or replanned; cancelled dependencies are excluded from active readiness.

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
