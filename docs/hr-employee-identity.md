# HR Employee Identity

BIWMS employee identity management keeps employee access cards separate from the core employee master record.

## Architecture

```text
HR
└── Employee Identity
    ├── ID Cards
    ├── Card Templates
    ├── Card Printing
    ├── Card History
    └── Verification Logs
```

The `employee_id_cards` table is the source of truth for issued cards. Existing ID card columns on `employees` remain for compatibility and are mirrored from the active card.

## Card Lifecycle

Supported statuses:

- `draft`
- `active`
- `expired`
- `lost`
- `revoked`
- `replaced`

Issuing a card creates one active card for the employee. Replacing a card marks the old card as `replaced` and creates a new active card. Revoked or lost cards do not verify as active.

## Templates

Card templates provide controlled presets:

- orientation
- width and height in millimeters
- colors
- logo, photo, and QR placement presets
- visible employee fields
- default/active flags

Drag-and-drop template design is intentionally out of scope for this phase.

## Printing

Card printing supports print batches with:

- selected cards
- template selection
- layout values such as `single`, `2-up`, and `4-up`
- batch history
- print count updates

Preview, print, and PDF download use one shared Blade template. Browser preview/print uses SVG QR codes, while PDF output uses PNG data URIs for DomPDF compatibility.

## Verification

Public verification uses:

```text
/employee-card/verify/{token}
```

Verification logs store only operational data:

- card id
- verified timestamp
- result
- IP address
- user agent
- optional device/location code

Verification output does not expose payroll, salary, bank, or other sensitive HR data.

## History

Card history records lifecycle events:

- issued
- previewed
- downloaded
- printed
- verified
- regenerated
- lost
- revoked
- replaced
- expired

Audit trail entries remain available for security and compliance reporting.

## Future Extensions

This structure can later support attendance scanning, access-control devices, visitor management, and card inventory workflows without changing payroll logic.
