# Financial Statement Business Isolation

Core financial statement services are G/L-entry driven. Business-specific
browser, print, and export requests resolve an explicit `business_id` first,
then the active session business, and pass that value through the same report
calculation service.

The business filter is applied to `gl_entries.business_id` for Trial Balance,
General Ledger, Profit & Loss, Balance Sheet, Cash Flow, and Group Summary.
Statement totals use the G/L debit and credit columns in local currency; they
do not use cached Chart of Account balances.

`BankAccount` does not currently persist `business_id`. Cash Flow therefore
derives its cash-account universe from bank-linked G/L accounts with activity
in the selected business. Persisted bank ownership is a follow-up schema
decision; reports must not infer ownership from an arbitrary first business.

Account schedule formulas retain the existing validated expression evaluator.
The evaluator was not expanded in this phase; a separate security hardening
pass should replace or further constrain dynamic formula evaluation.
