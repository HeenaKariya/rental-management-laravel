# PMS v1.5 Laravel Implementation Phases

This plan is Laravel-first, dependency-ordered, and release-ready.  
Goal: build security and data integrity first, then business flows, then compliance/reporting.

## Working Assumptions

- Backend: Laravel 11, PHP 8.2+, MySQL 8
- Auth stack: Laravel Fortify + Sanctum
- UI stack: Blade + Livewire + Alpine (recommended for this scope)
- Queue/Scheduler: Laravel Queue + Scheduler
- Files: Laravel Filesystem (local for dev, S3 for production)
- Notifications: Mail + WhatsApp provider abstraction
- Testing: Pest or PHPUnit Feature/Unit tests

## Phase 0: Project Bootstrap and Architecture Lock

### Objectives
- Create Laravel project baseline and coding standards.
- Finalize architecture choices and module boundaries.
- Define FR traceability approach from SRS to tickets/tests.

### Deliverables
- Environment profiles: local/staging/production.
- Base folder structure for modules.
- Role-permission matrix for Super Admin, Manager, Owner, Tenant.
- Decision document:
  - Blade+Livewire confirmed (or SPA if you choose otherwise).
  - Queue driver, cache driver, file driver.
- FR traceability sheet: each FR mapped to epic + acceptance test.

### Exit Criteria
- Team can run project locally.
- CI pipeline runs lint + tests successfully.
- Traceability baseline approved.

---

## Phase 1: Authentication, RBAC, and 2FA Security Core

### Objectives
- Implement all login/security requirements first.
- Enforce strict pre-session to full-session lifecycle.

### Scope
- FR-AUTH-01 to FR-AUTH-07
- FR-2FA-01 to FR-2FA-11

### Deliverables
- User auth with password reset and tenant invite flow.
- Role-based middleware and policy gates.
- Pre-session token model with 15-minute TTL.
- OTP flows (email/WhatsApp), resend limits, fallback logic.
- Backup code generation/use/regeneration.
- Soft lock and hard lock flows.
- Super Admin 2FA management panel.
- 2FA audit logs and read-only admin view.

### Exit Criteria
- No protected route accessible with pre-session token.
- All 2FA edge cases tested (delivery failure, lockouts, fallback).
- Security review sign-off for auth module.

---

## Phase 2: Property Core and Manager Assignment

### Objectives
- Build property domain and access scoping foundation.

### Scope
- FR-PROP-01 to FR-PROP-06

### Deliverables
- Property CRUD with lifecycle stage tracking.
- Property photo handling (cover + gallery + order).
- Soft archive behavior.
- Manager-property assignment and instant revoke behavior.
- Assignment logs and assignment notifications.
- Manager-scoped property visibility across listings/details.

### Exit Criteria
- Manager cannot access unassigned property data via URL/API.
- Assignment and removal fully audited.

---

## Phase 3: Tenant, Unit, Lease, and Deposit Foundation

### Objectives
- Establish core operational entities and invariants.

### Scope
- FR-UNIT-01 to FR-UNIT-04
- FR-TEN-01 to FR-TEN-07
- FR-LEASE-01 to FR-LEASE-07
- FR-DEP-01 to FR-DEP-09

### Deliverables
- Unit and tenant management with KYC document support.
- Lease lifecycle and expiring-soon rules.
- Renewal flow with transactional prior-lease state update.
- Deposit sub-ledger: collection, deduction, refund, forfeiture, top-up.
- Tenant portal visibility for lease/deposit/KYC.

### Exit Criteria
- Single active lease per unit enforced at DB and app levels.
- Renewal cannot create overlapping active leases.
- Deposit balances always reconcile.

---

## Phase 4: Rent Ledger, Instalments, Arrears, Credits, Void Controls

### Objectives
- Implement rent engine with strong accounting behavior.

### Scope
- FR-PAY-01 to FR-PAY-11
- FR-RENTRET-01 to FR-RENTRET-09

### Deliverables
- Monthly rent ledger generation.
- Instalment recording and receipt generation.
- Arrears carry-forward and overpayment credit handling.
- Pro-rata Rent Return calculation (daily rate x unused days) with suggested vs confirmed amount tracking.
- Rent Return record lifecycle: Initiated, Confirmed, Settled, Waived, Pending Settlement.
- Settlement methods: cash refund, adjust against arrears, adjust against deposit, write-off, pending.
- Optional ledger posting control for Rent Return reversal entries.
- Vacancy-gap protection checks and quick action during new lease creation.
- Tenant portal Rent Return visibility and summary PDF availability after confirmation.
- Correction log behavior (no direct amount edit).
- Full ledger void flow with prerequisites, password confirm, and reason.
- Downstream arrears review flags for void impacts.

### Exit Criteria
- Ledger math validates for all scenarios.
- Rent Return calculations reconcile with vacation date and paid-through date inputs.
- Voided entries are terminal and audit-complete.
- Payment timeline is accurate for each lease.

---

## Phase 5: Financials, Ownership, Purchase/Sale, Expense Review Flags

### Objectives
- Complete owner economics and financial governance.

### Scope
- FR-PUR-01 to FR-PUR-06
- FR-OWN-01 to FR-OWN-05
- FR-SALE-01 to FR-SALE-05
- FR-FIN-01 to FR-FIN-06

### Deliverables
- Purchase records, loans, EMI logs, ownership splits.
- Sale lifecycle and auto profit/loss calculations.
- Property ledger (income/expense) with auto-linked events.
- Expense auto-flagging rules and Super Admin review queue.
- Approved/rejected flagged expense workflow with audit trail.

### Exit Criteria
- Ownership totals validated at 100 percent.
- P&L reflects approved/rejected expense behavior correctly.
- Owner read-only views are scoped and correct.

---

## Phase 6: Digital Agreement, E-Sign, PDF Hash Integrity

### Objectives
- Deliver compliant digital agreement lifecycle with tamper detection.

### Scope
- FR-ESIGN-01 to FR-ESIGN-13

### Deliverables
- Agreement templates and placeholder engine.
- Tokenized public signing page with validation.
- Signature capture and signed PDF generation.
- Notarized agreement upload and status indicators.
- SHA-256 fingerprint store on signed PDF creation.
- Verify Integrity action and pass/fail audit events.

### Exit Criteria
- Only one active unsigned agreement per lease.
- Hash mismatch is detected and logged consistently.
- Tenant receives signed PDF after signing.

---

## Phase 7: Notifications, Reports, Scheduler, and Audit Read Models

### Objectives
- Complete communication and reporting layer.

### Scope
- FR-NOTIF-01 to FR-NOTIF-04
- FR-FIN-04 to FR-FIN-05
- Related report/export requirements from deposit/rent modules

### Deliverables
- Event-driven notifications by role/channel.
- Delivery log with status tracking.
- Scheduled reminders for rent, lease, KYC, EMI, deposits.
- Reports: P&L, rent collection, expenses, owner statement, loan schedule, arrears, deposits.
- Rent Return report with filters (property, date range, status) and settlement/ledger-posting visibility.
- PDF/CSV export capability for all required reports.

### Exit Criteria
- Scheduler jobs run reliably in staging.
- Delivery failures are visible and retriable.
- Reports reconcile with transactional data.

---

## Phase 8: UAT, Hardening, and Go-Live

### Objectives
- Validate complete product behavior under real scenarios.

### Deliverables
- UAT scripts mapped to all FR items.
- Performance checks for NFR targets.
- Security checklist (OWASP controls, access tests, signed URLs).
- Backup/restore rehearsal.
- Deployment runbook and rollback runbook.
- Production monitoring and alert setup.

### Exit Criteria
- UAT sign-off by Super Admin and Manager personas.
- Critical and high defects resolved.
- Go-live checklist fully passed.

---

## Recommended Migration Sequence (Laravel)

1. users, roles/permissions, auth support tables  
2. user_2fa, pre_sessions, otp_tokens, backup_codes  
3. properties, property_managers, property_owners  
4. units, tenants, tenant_documents  
5. leases, lease_deposits, deposit_deductions, deposit_refunds  
6. rent_ledger, rent_instalments, lease_credits, rent_returns  
7. property_purchases, loan_emis, property_sales, sale_leads  
8. expenses, maintenance_requests  
9. agreement_templates, rent_agreements, notarized_agreements  
10. documents, notifications_log, audit_logs

---

## Sprint Model Recommendation

- Sprint length: 2 weeks
- Suggested mapping:
  - Sprints 1-2: Phase 0-1
  - Sprint 3: Phase 2
  - Sprints 4-5: Phase 3
  - Sprint 6: Phase 4
  - Sprint 7: Phase 5
  - Sprint 8: Phase 6
  - Sprint 9: Phase 7
  - Sprint 10: Phase 8

---

## Non-Negotiable Gates Before Go-Live

1. Pre-session cannot access app routes.
2. No overlapping active leases.
3. Ledger void and arrears behavior fully audited.
4. Signed PDF integrity verification works.
5. Role-based access passes URL/API bypass tests.
6. Backup and restore drill completed.
7. Rent Return records reconcile with ledger and lease closure data.