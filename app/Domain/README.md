# Domain Modules

Phase 0 establishes `app/Domain/<Module>/` as the project boundary for business modules.

Current baseline modules:

- `Auth`: authentication, 2FA, pre-session, security rules
- `Property`: properties, assignments, owners
- `Tenancy`: tenants, units, leases, deposits
- `Finance`: ledger, rent returns, expenses, ownership economics
- `Agreement`: digital agreements, signatures, integrity checks
- `Shared`: reusable concerns, support classes, cross-cutting utilities

Each module should grow with its own actions, DTOs, policies, services, value objects, and read models as implementation advances.
