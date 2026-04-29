# Phase 4 Notes

Status: In Progress
Date: 2026-04-29

## Objective

- Establish the finance foundation on top of the lease domain.
- Start with monthly rent ledgers and instalments because arrears, credits, receipts, and rent returns all depend on payment-history state.

## Current Implementation Slice

- Lease finance settings now include grace period days and late-fee rules.
- Monthly rent ledger entries auto-generate for non-draft leases across the lease period.
- Instalments can be recorded manually against any generated ledger month.
- Instalments now expose downloadable PDF receipts for staff and tenant-visible payment history.
- Ledger totals now recalculate total due, received, late fees, outstanding balance, arrears carry-forward, and overpayment credit carry-forward.
- Lease payment history is available to managers, super admins, and tenants in read-only mode for tenants.
- Focused feature tests cover generation, partial payments, arrears carry-forward, overpayment credit carry-forward, tenant read-only access, and receipt downloads.

## Remaining Deliverables

- Correction log and void controls.
- Rent dashboard views across properties and units.
- Rent return calculation and settlement lifecycle.
- Vacancy-gap protection and lease-creation quick action.

## Exit Criteria Status

- Ledger math validates for all scenarios: In progress
- Rent Return calculations reconcile with vacation date and paid-through date inputs: Not started
- Voided entries are terminal and audit-complete: Not started
- Payment timeline is accurate for each lease: In progress

## Notes

- The first Phase 4 slice attaches directly to the lease because billing day, tenant scope, and tenant portal visibility already exist there.
- Arrears and credits are recalculated sequentially across the ledger timeline whenever a new instalment is recorded.
