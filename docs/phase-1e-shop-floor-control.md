# Phase 1E Shop Floor Control / MES Lite

Phase 1E adds operational shop-floor execution for released production orders without changing the accounting ownership model.

## Scope

Phase 1E supports one production order, one BOM, one routing, and one finished item. It covers operator assignments, setup and run timers, manual time, scrap, downtime, rework, quality checks, quality holds, shift handover records, progress summaries, production journal handoff, and report-only reconciliation.

Phase 2 exclusions remain out of scope: child production orders, multi-level production execution, MRP/MPS/APS, finite-capacity scheduling, routing networks across orders, SCADA/IoT, predictive maintenance, AI optimization, offline sync, and OEE unless all inputs become reliable.

## Architecture

The authoritative flow is:

```text
Manufacturing event
-> ProductionOperationExecution / operational detail records
-> ProductionJournalBatch / ProductionJournalLine
-> ItemLedgerEntry or CapacityLedgerEntry
-> ValueEntry
-> ValueEntryAccountingOrchestrator
-> Posting Kernel / G/L
```

Shop-floor tables do not post G/L entries. Filament actions delegate to `ProductionOperationExecutionService`. Item, capacity, value, and G/L ownership remains with the existing journal and value-entry architecture.

## State Machine

Execution statuses:

```text
not_started -> ready -> setup_started -> setup_paused -> setup_started
setup_started -> setup_completed -> running -> paused -> running
running -> completed -> submitted -> posted
submitted|posted -> reversed
not_started|ready|setup_started|setup_paused|setup_completed|running|paused|completed -> cancelled
```

Transitions are enforced by `ProductionOperationExecutionStatus` and `ProductionOperationExecutionService`, not only by UI visibility.

## Operational Capture

Setup, run, labour, machine, and downtime durations are stored as seconds. Display can convert to hours/minutes. Machine time is not multiplied by operator count; labour time can sum across operators through manual or timer entries.

Timers create operational time entries only. Capacity ledger entries are created later through production journal posting.

## Journal Workflow

Completed executions may be submitted with a generated production journal batch. The generated journal lines reference `production_operation_execution_id` and carry a shop-floor idempotency key.

Generated shop-floor journals include the operational lines needed for the one-order, one-routing Phase 1E scope:

- consumption lines for production order components;
- capacity lines for captured setup/run time;
- output lines for good quantity.

Posting uses the existing `ProductionJournalPostingRoutine`, which owns the transition from journal lines to `ItemLedgerEntry` and `CapacityLedgerEntry`. Those ledger entries then create `ValueEntry` records and are posted to G/L through the value-entry accounting orchestrator. Retrying an already posted shop-floor journal is idempotent and must not duplicate item, capacity, value, or G/L entries.

Auto-posting is not enabled by default. Corrections and reversals must remain append-only.

Posting failure is transactional. If value-entry or G/L posting fails, the execution remains submitted, the journal remains unposted, and no partial ledger entries should be committed.

## Quality, Scrap, Rework, Downtime

Scrap and downtime reasons are controlled setup records. Scrap has a stage and posting treatment, but Phase 1E does not directly expense scrap from the shop-floor record. Quality failures can place active holds; active holds block operation completion until released by an authorized user. Quality check attachments are stored on the private local disk, validated by MIME type and size, and downloaded only through an authenticated, authorized route. Attachment paths are never exposed as public storage URLs.

Rework is recorded within the same production order. It does not create child production orders in this phase.

## Progress and Readiness

`ProductionProgressService` derives progress from execution records and ledgers. `ProductionCompletionReadinessService` returns structured findings such as operation not completed, journal generation pending, journal batch missing, missing consumption/capacity/output journal lines, active quality hold, pending quality check, submitted operator time, open downtime, and open rework.

## Reconciliation

Run:

```bash
php artisan biwms:shop-floor-reconcile --details
php artisan biwms:shop-floor-reconcile --details --export=storage/app/reports/shop-floor-reconcile.json
```

The command is report-only. It classifies issues such as duplicate starts, operator or machine time overlap, missing journals, unposted journals, posted journal lines missing ledger links, missing capacity value entries, manufacturing value entries not posted to G/L, active quality holds, open downtime, open rework, sequence violations, broken reversal chains, and finished orders with open execution.

## Permissions

Standard CRUD permissions are generated from Filament resource metadata. Special shop-floor permissions are seeded for assignment, start, pause, resume, complete, submit, approve, post, reverse, sequence override, quantity override, time correction, scrap approval, rework approval, downtime approval, quality recording, quality-hold release, and production-progress access.

## Migration Notes

The Phase 1E migration is PostgreSQL-safe and idempotent. It does not backfill old production orders with pretend execution history. It adds nullable journal-line traceability to shop-floor executions.

The known PostgreSQL `max_locks_per_transaction` limitation during some fresh test rebuilds is infrastructure-related unless a Phase 1E migration fails independently.

## Example

Production Order: produce 1,000 bottles.

Operation 10, Mixing:

```text
Setup: 30 minutes
Run: 2 hours
Operators: 2
Machine time: 2 hours
Labour time: 4 labour-hours
Downtime: 15 minutes
```

Operation 20, Filling:

```text
Good output: 970 bottles
Scrap: 20 bottles
Rework: 10 bottles
```

Material consumption, capacity, and output are submitted through production journal lines. The quality inspection passes, the journal is approved and posted, and the production order can be completed once readiness findings are clear.
