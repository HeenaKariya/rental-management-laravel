# Phase 3 Notes

Status: Complete
Date: 2026-04-29

## Objective

- Establish the tenancy foundation on top of the Phase 2 property scope.
- Start with unit inventory because tenants, leases, and deposits all depend on unit-level invariants.

## Current Implementation Slice

- Unit schema with property-level uniqueness on unit number.
- Unit CRUD for Super Admin and manager roles.
- Manager-scoped unit visibility inherited from assigned property scope.
- Tenant schema with unit-linked records and KYC document uploads.
- Tenant CRUD for Super Admin and manager roles.
- Manager-scoped tenant visibility inherited from assigned unit and property scope.
- Lease schema with unit and tenant binding plus successor renewal linkage.
- Single active lease per unit enforced at both DB and app levels.
- Renewal flow updates the prior active lease transactionally before creating the successor active lease.
- Deposit accounts now attach one-to-one to leases and maintain reconciled running balances.
- Deposit ledger entries support collection, top-up, deduction, refund, and forfeiture flows.
- Tenant portal now exposes self-owned tenant, lease, and deposit records in read-only mode.
- Dashboard-style unit workspace pages for listing, create, edit, and detail flows.
- Dashboard-style tenant workspace pages for listing, create, edit, and detail flows.
- Dashboard-style lease workspace pages for listing, create, edit, and detail flows.
- Dashboard-style deposit workspace pages for listing, create, and detail flows.
- Focused feature tests for create, visibility, unauthorized create attempts, uniqueness, assigned-manager updates, KYC file persistence, lease invariant enforcement, deposit reconciliation, and tenant portal visibility.

## Remaining Deliverables

- None.

## Exit Criteria Status

- Single active lease per unit enforced at DB and app levels: Complete
- Renewal cannot create overlapping active leases: Complete
- Deposit balances always reconcile: Complete

## Notes

- Phase 3 starts from units because later tenancy records must attach to a property-scoped inventory object.
- Existing property assignment rules now gate the first tenancy surface without reopening Phase 1 or Phase 2 boundaries.
