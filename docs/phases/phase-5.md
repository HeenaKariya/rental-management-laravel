# Phase 5 Notes

Status: In Progress
Date: 2026-04-29

## Objective

- Establish owner economics and property-level financial governance on top of completed tenancy and rent ledgers.

## Current Implementation Slice

- Property-level owners can now be captured as linked owner users or named external investors.
- Ownership sync enforces exactly 100 percent total share before saving.
- Property ledger entries now support income and expense lines with per-property scope.
- Rent instalments now auto-post income entries to the relevant property ledger.
- Voided instalments now auto-post rent reversal entries for audit-complete ledger traceability.
- Expense entries use auto-flag rules and enter a Super Admin review queue when thresholds are exceeded.
- Super Admin review can approve or reject pending flagged expenses with review notes.
- Finance dashboard now surfaces flagged expense queue visibility.
- Purchase records now store acquisition cost components and auto-calculate total acquisition cost.
- Loan setup and EMI payment logs are now tracked per property, with loan summary metrics (EMIs paid, interest paid, outstanding principal, remaining tenure).
- Logged EMI payments auto-post loan EMI expense entries into the property ledger.
- Sale listing now captures asking price, listing context, and broker details.
- Buyer lead and offer interactions can be logged with lifecycle statuses.
- Sale closure now computes net proceeds and gross profit/loss, then auto-transitions property lifecycle to sold.
- Sold lifecycle now blocks new tenant and lease creation to enforce read-only operational state.
- Owner statement and category-level P&L matrix are now available as on-screen views with CSV/PDF exports.

## Remaining Deliverables

- None in this phase slice.

## Exit Criteria Status

- Ownership totals validated at 100 percent: In progress
- P&L reflects approved/rejected expense behavior correctly: In progress
- Owner read-only views are scoped and correct: In progress
