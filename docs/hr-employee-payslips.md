# HR Employee Payslips

BIWMS manages payslips as immutable payroll snapshots under HR/Payroll.

## Architecture

- `PayrollDocument` is the payroll run or batch.
- `PayrollLine` is the employee payroll result line.
- `EmployeePayslip` is the issued payslip header.
- `EmployeePayslipEarning` and `EmployeePayslipDeduction` store printed snapshot lines.
- `EmployeePayslipHistory` records generation, print, download, revoke, and regenerate events.

Payslips are generated only from approved or posted payroll documents. Draft, open, and calculated payroll documents should not be issued to employees.

## Lifecycle

1. Calculate payroll.
2. Approve or post the payroll document.
3. Generate payslips from HR/Payroll > Employee Payslips.
4. Preview, print, or download the payslip PDF.
5. Revoke or regenerate only through authorized, password-confirmed actions.

The generated payslip stores employee identity, company information, earnings, deductions, gross pay, deductions, and net pay as they were at issue time.

## Security

- Payslips never expose payroll setup secrets, passwords, tokens, or bank details.
- Revocation and regeneration require password confirmation.
- Linked employee users may view or download only their own payslips.
- HR users require explicit `hr.employee_payslip.*` permissions.
- Every generate, print, download, revoke, and regenerate action is audited.

## Templates

Phase 1 uses one professional default Blade template shared by preview, print, and PDF download. Template management and drag-and-drop layout design are intentionally deferred.

## Operational Notes

- Do not edit generated payslip rows directly.
- Correct payroll errors through payroll correction/reversal processes, then regenerate the affected payslip.
- PDF logo rendering uses the same storage-safe image handling as Employee ID cards.
