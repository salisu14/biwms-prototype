# Manufacturing Phase 2B Completion

## Scope

Phase 2B is coordination-only. It does not post inventory, value, capacity, or G/L records. Posting remains owned by the established production posting services, item application, value entry accounting orchestrator, and posting kernel.

## Dependency Mapping

Generated child-output dependencies resolve the downstream parent operation in this order:

1. Exact component routing link: parent component `routing_link_code` must match a parent routing line.
2. Safe inference: if the component has no routing link and the parent order has exactly one routing line, that routing line is used.
3. Unresolved mapping: if multiple downstream operations exist and the component is not mapped, no dependency or handoff is created. Reconciliation reports `unresolved_dependency_mapping`.

This prevents an intermediate item from silently feeding the first parent operation when it actually belongs to a later operation such as Filling, Tray Packing, or Carton Packing.

## Readiness Lifecycle

Operation start readiness is derived from active dependencies, upstream order state, supply availability, quality holds, and minimum start quantity.

Operator-facing blockers are simplified to:

- waiting for previous stage;
- waiting for material/output;
- waiting for quality;
- blocked by cancelled upstream;
- dependency mapping requires review.

Invalid or upstream-cancelled dependencies remain blocking until cancelled or replanned. Cancelled dependencies are excluded from active readiness.

## Partial Handoffs

Supply link production and consumption remain authoritative. Dependency and handoff progress is synchronized from those records.

Available and consumed quantities are capped at the dependency required quantity. Overproduction remains visible on the supply link but does not overfulfil the dependency.

Repeated generation and progress syncs are idempotent by dependency and handoff idempotency keys.

## Quality, Cancellation, and Reversal

Active upstream quality holds block downstream readiness even when physical output exists. Releasing the hold allows readiness to re-evaluate normally.

Cancelled upstream orders invalidate the dependency and continue to block downstream operations. Downstream cancellation does not delete upstream history.

Output and consumption reversals are handled through the existing supply-link, reservation, and item-application synchronization paths. Reversed item applications are excluded from active genealogy traversal.

## Genealogy

Backward genealogy starts from finished output `ItemLedgerEntry`, follows production consumption entries, then follows non-reversed `ItemApplicationEntry` source layers. If a source layer is itself production output, traversal continues into that child order.

Forward genealogy starts from an inbound item ledger layer, follows non-reversed applications to production consumption, then follows the consuming production order outputs.

Traversal is bounded and guarded by visited ledger entries to avoid cycles.

## Reconciliation

`php artisan biwms:manufacturing-hierarchy-reconcile --details` remains report-only. It now reports:

- unresolved dependency mapping;
- missing routing references;
- direct or indirect dependency cycles;
- duplicate active dependencies;
- fulfilled dependencies with insufficient supply;
- quality-blocked dependencies marked ready;
- downstream start/completion before readiness;
- stale dependency status;
- handoff quantity drift;
- orphan handoffs or dependencies;
- missing genealogy ledger references;
- cancelled upstream orders with ready dependencies.

## Phase 2B Completion Status

Phase 2B is complete when the test suite and reconciliation confirm:

- no silent downstream-operation fallback;
- idempotent dependency and handoff generation;
- partial handoff and consumption progress remain cumulative;
- quality and cancellation block execution correctly;
- forward and backward genealogy exclude reversed applications;
- single-level production orders remain unaffected;
- no Phase 2C scheduling behavior is required.
