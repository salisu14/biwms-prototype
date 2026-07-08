# BIWMS Responsive UI Audit

## Scope Reviewed

- Admin panel: dashboard filters, Role edit, User Security/MFA.
- Finance panel: finance report pages and ledger-heavy table patterns.
- Sales panel: Sales Order, Sales Invoice, Posted Sales Invoice history/view.
- Procurement panel: Purchase Order and Purchase Invoice.
- Factory panel: Production Order.
- Warehouse panel: inventory/item list and inventory-heavy table patterns.
- HR, Project, and Service panels: dashboard/widget/table patterns were reviewed at a high level for consistent Filament behavior.

## Issues Found

- Several priority forms used fixed `2`, `3`, or `4` column layouts that squeezed fields on mobile/tablet.
- Sales and purchase line repeaters used wide fixed column counts.
- Sales/Purchase/Posted document tables kept too many secondary columns visible by default.
- Some row action sets rendered multiple inline buttons, which wraps poorly on narrow screens.
- The Role permission editor had already been optimized for payload size, but its module/search/resource controls still used a fixed three-column row.
- User Security/MFA table displayed secondary information and multiple sensitive actions inline.
- Admin dashboard filters were not explicitly breakpoint-aware.

## Fixes Applied

- Converted priority form grids to responsive breakpoints using one mobile column and wider tablet/desktop columns.
- Updated repeaters for Sales Order, Sales Invoice, Purchase receiving, and Item setup to stack on mobile.
- Grouped row actions with `ActionGroup` for Sales Order, Sales Invoice, Posted Sales Invoice, Purchase Invoice, and User Security.
- Marked secondary date/type/detail columns as toggleable and hidden by default on high-density tables.
- Reduced default visible inventory table columns while preserving access through the column manager.
- Updated Role edit controls and checkbox matrix to use responsive breakpoints.
- Made Admin dashboard filters stack on mobile and spread on tablet/desktop.
- Added regression tests to guard responsive grids, grouped actions, and toggleable secondary columns on priority pages.

## Remaining Known Limitations

- Some report Blade tables intentionally remain horizontally scrollable because financial reports require column fidelity.
- Many generated or lower-traffic resources still need a deeper page-by-page visual pass with real browser screenshots.
- Mobile usage should focus on review, approvals, lookups, and light edits; heavy line-entry workflows are improved but still best on tablet/desktop.
- Relation managers with very wide operational data may need resource-specific column trimming in a later pass.
- A Playwright-style visual audit across all panel routes would provide stronger coverage than static regression tests.

## Follow-Up Checklist

- Run a manual browser pass at common widths: 390px, 768px, 1024px, and desktop.
- Prioritize any remaining overflow found in finance reports, relation managers, and custom Blade document views.
- Keep new resources on responsive grids:
  - `columns(['default' => 1, 'md' => 2, 'xl' => 3])`
  - `Grid::make(['default' => 1, 'md' => 2])`
  - grouped row actions for more than two actions
  - toggleable secondary table columns
