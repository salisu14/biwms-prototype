# Business Exception and User-Friendly Error Handling

BIWMS uses `App\Exceptions\BusinessException` for expected business-rule and setup failures that users can correct. Examples include missing number-series setup, incomplete posting setup, insufficient stock, invalid document state, or no remaining quantity to invoice.

Do not use `BusinessException` for defects, database failures, coding errors, or ledger integrity defects. Those should remain real exceptions so they are visible in logs and monitoring.

## Standard Behavior

- Web and Filament requests receive validation-style feedback instead of a generic 500.
- JSON/API requests receive a structured 422 or 409 response with `message`, `title`, and `errors`.
- Expected business exceptions are logged at info/warning level, not as production error noise.
- Existing database transactions still roll back when a business exception is thrown.

## Canonical Exception Types

- `BusinessException`: generic expected business failure.
- `NumberSeriesException`: missing, inactive, exhausted, blocked, or date-invalid number series.
- `MissingNumberSeriesException`: compatibility subclass of `NumberSeriesException`.
- `DocumentStateException`: invalid status transition or action for a document.
- `PostingSetupException`: missing posting setup, control account, bank account G/L, or related configuration.

## Service Guidelines

Throw a business exception when the user or administrator can fix the condition through setup or workflow:

- number series missing or exhausted;
- document already posted or not approved;
- posting group/account setup missing;
- insufficient available quantity or bank balance;
- no invoiceable quantity remains.

Keep regular exceptions for unexpected technical failures:

- missing value entries created by a required posting routine;
- database constraint failures;
- invalid code paths;
- infrastructure failures.

## Filament Guidelines

Filament actions and pages may catch `BusinessException` when a contextual notification improves the experience. They should rethrow or convert it to validation errors so the operation still fails atomically.

Never catch `Throwable` broadly just to show a friendly message. Unexpected errors must remain visible to operators.
