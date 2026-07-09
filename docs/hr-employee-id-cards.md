# HR Employee ID Cards

BIWMS can generate printable employee ID cards from the employee master data and company profile.

## Generating Cards

1. Open **HR > Employees**.
2. Select an employee.
3. Use **Generate ID Card** when no card has been issued yet.
4. Use **Preview ID Card**, **Print ID Card**, or **Download ID Card PDF** for issued cards.
5. Use the bulk **Download ID Cards** action to create a multi-page PDF for selected employees.

Regenerating an ID card creates a new secure token and requires password confirmation.

## QR Code Purpose

The QR code contains a compact signed payload:

```text
employee_number|id_card_number|token|signature
```

The signature is generated with the application key. The payload does not include salary, bank details, payroll data, or other sensitive personal information.

## Verification Behavior

The public verification page is:

```text
/employee-card/verify/{token}
```

It shows only safe identity information:

- employee photo
- employee name
- employee number
- department
- job title
- card status
- company name

Expired, revoked, inactive, or unknown cards are shown as not active.

## Security Notes

- ID card download and generation require HR permissions.
- Regeneration requires password confirmation.
- Card generation, regeneration, download, and verification are audited.
- QR images are rendered on demand and are not stored permanently.
- Card tokens are never written to audit metadata.

## Future Extensions

The same tokenized card foundation can later support:

- attendance scan-in and scan-out
- visitor/access-control integrations
- device-based verifier apps
- card revocation workflows
