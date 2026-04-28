# Role Permission Matrix

Date: 2026-04-28

This Phase 0 matrix defines the access baseline that Phase 1 will implement in code.

| Capability | Super Admin | Manager | Owner | Tenant |
| --- | --- | --- | --- | --- |
| Manage platform settings | Yes | No | No | No |
| View assigned properties | Yes | Yes | Yes (owned only) | No |
| Manage property records | Yes | Yes (assigned only) | No | No |
| Assign managers to properties | Yes | No | No | No |
| View units and occupancy | Yes | Yes (assigned only) | Yes (owned only) | Limited (own unit only) |
| Manage tenants and KYC | Yes | Yes (assigned only) | No | No |
| Manage leases and renewals | Yes | Yes (assigned only) | No | No |
| Record rent and ledger events | Yes | Yes (assigned only) | No | No |
| Approve flagged expenses | Yes | No | No | No |
| View owner-level statements | Yes | No | Yes (owned only) | No |
| View tenant portal data | Yes | No | No | Yes (self only) |
| Manage 2FA policies and recovery | Yes | Limited self-service | Limited self-service | Limited self-service |

## Implementation Direction

- Access rules will be enforced at both route and policy level.
- Manager and owner access must always be scoped by assignment/ownership.
- Tenant access must always be self-scoped.
