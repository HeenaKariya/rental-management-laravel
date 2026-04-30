# Phase 4 Notes

Status: Complete
Date: 2026-04-29

## Objective

- Establish the finance foundation on top of the lease domain.
- Start with monthly rent ledgers and instalments because arrears, credits, receipts, and rent returns all depend on payment-history state.

## Current Implementation Slice

- Lease finance settings now include grace period days and late-fee rules.
- Monthly rent ledger entries auto-generate for non-draft leases across the lease period.
- Instalments can be recorded manually against any generated ledger month.
- Instalments now expose downloadable PDF receipts for staff and tenant-visible payment history.
- Metadata-only corrections now log old and new payment values without allowing amount edits.
- Voiding an instalment is password-confirmed, reasoned, terminal, and triggers ledger recalculation.
- Staff finance dashboard now exposes upcoming dues, partially paid, overdue, arrears tracker, and recently recorded payment views with property/unit filters.
- Lease-scoped rent returns now support initiated, confirmed, settled, waived, and pending-settlement states with pro-rata suggestions based on vacation date versus paid-through date.
- Rent return summary PDFs are available after confirmation for staff and tenant-visible lease records.
- New lease creation now shows vacancy-gap notices for prior terminated tenants, links directly to the prior rent-return action, and enforces non-overlapping start dates against the previous vacation date.
- Ledger totals now recalculate total due, received, late fees, outstanding balance, arrears carry-forward, and overpayment credit carry-forward.
- Lease payment history is available to managers, super admins, and tenants in read-only mode for tenants.
- Focused feature tests cover generation, partial payments, arrears carry-forward, overpayment credit carry-forward, tenant read-only access, receipt downloads, corrections, void controls, finance dashboard scoping, and rent return initiation/settlement.

## Remaining Deliverables

- None in this phase slice.

## Exit Criteria Status

- Ledger math validates for all scenarios: Complete
- Rent Return calculations reconcile with vacation date and paid-through date inputs: Complete
- Voided entries are terminal and audit-complete: Complete
- Payment timeline is accurate for each lease: Complete

## Notes

- The first Phase 4 slice attaches directly to the lease because billing day, tenant scope, and tenant portal visibility already exist there.
- Arrears and credits are recalculated sequentially across the ledger timeline whenever a new instalment is recorded.
- Phase 5 continuation starts with owner splits, property ledger entries, and flagged expense review queue handoff.
