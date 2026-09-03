# Business Context Authorization

BIWMS uses `user_businesses` as the explicit entitlement boundary. A selected
business ID is only a context request; it is accepted after the business is
found, active, and authorized for the current user.

Super Admins may select any active business by explicit policy. Other users
must have a `user_businesses` assignment. The migration preserves legacy
single-business installations deterministically by assigning all existing
users to the only active business when exactly one exists. It does not guess
assignments when multiple active businesses exist.

For compatibility with an un-migrated single-business test or installation,
an unassigned non-Super-Admin user is treated as entitled only when exactly
one active business exists. That implicit compatibility path is disabled as
soon as explicit multi-business access is present.

`BusinessContextService` is the canonical resolver for panel middleware,
services, controllers, and report/header callers. `CompanyInformationService`
uses it for report headers. Report reads do not create `Your Company Name`
placeholder records; profile creation remains an explicit setup operation.

Business access grants and revocations are managed from the User resource,
protected by the existing user-update authorization, and recorded in the
audit trail. Removing the final business from one's own non-Super-Admin account
is blocked to reduce accidental lockout.
