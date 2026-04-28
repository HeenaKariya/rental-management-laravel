# FR Traceability Baseline

Date: 2026-04-28

This baseline maps the major requirement groups from the implementation plan to the implementation epics that should own them.

| Requirement Group | Epic / Module | Initial Acceptance Target |
| --- | --- | --- |
| FR-AUTH-* | Auth / identity baseline | Login, logout, password reset, invite-ready user creation |
| FR-2FA-* | Auth / two-factor security | Challenge flow, backup codes, lockout rules |
| FR-PROP-* | Property | CRUD, lifecycle, manager assignment |
| FR-UNIT-* | Tenancy | Unit CRUD and occupancy state |
| FR-TEN-* | Tenancy | Tenant lifecycle and KYC records |
| FR-LEASE-* | Tenancy | Lease lifecycle and renewal invariants |
| FR-DEP-* | Tenancy / Finance | Deposit sub-ledger and balance reconciliation |
| FR-PAY-* | Finance | Rent ledger, instalments, receipts, arrears |
| FR-RENTRET-* | Finance | Rent return calculation and settlement flow |
| FR-PUR-* | Finance | Purchase, loans, and capital records |
| FR-OWN-* | Finance | Ownership splits and owner visibility |
| FR-SALE-* | Finance | Sale lifecycle and profit/loss logic |
| FR-FIN-* | Finance | Statements, flags, and financial governance |
| FR-ESIGN-* | Agreement | Template, signing, PDF integrity |
| FR-NOTIF-* | Shared / Notifications | Event-driven delivery and logs |

## Test Mapping Rule

- Each FR group should end with at least one feature test file that proves the business-critical acceptance path.
- Security-sensitive FR groups must also include negative-path tests.
