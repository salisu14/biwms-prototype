# Manufacturing Shop Floor Guide

Use the Factory panel to manage shop-floor execution for released production orders.

## My Work Queue

Open the Shop Floor area and review available or assigned operations. Search by production order number, work center, machine center, or operator assignment.

Only released production orders appear in the queue.

## Start Work

Open an operation execution and use:

```text
Start Setup
Complete Setup
Start Run
Pause / Resume
Complete
```

Use pause when work stops temporarily. Do not leave timers open at the end of a shift.

## Record Production Activity

Record good output, scrap, downtime, rework, and quality checks from the execution screen or related shop-floor actions.

Use controlled reasons for scrap and downtime. If a quality hold is active, the operation cannot be completed until the hold is released.

Attach quality evidence only through the authorized quality-check attachment action. Files are stored privately and downloaded through the application; do not share storage paths directly.

## Submit Journal

After completing an operation, submit it to create a production journal batch when required. The journal is then reviewed, approved, and posted through the normal production journal workflow.

Generated shop-floor journal batches include component consumption, captured capacity time, and finished output where those values exist on the execution. Retrying a posted journal should not create duplicate ledger entries.

If validation errors appear, correct the operational record or journal line through the approved correction process.

## Shift Handover

Use handover notes to tell the next operator what happened, what is still open, and whether any downtime, rework, or quality hold needs attention.

## Important Limits

The shop-floor pages support normal keyboard barcode scanners, but Phase 1E does not provide camera scanning, hardware integration, or offline synchronization.
