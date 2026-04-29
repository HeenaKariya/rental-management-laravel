# Phase 1 Notes

Status: In Progress
Date: 2026-04-28

## Objective

- Implement authentication, RBAC, and 2FA security core.
- Enforce strict pre-session to full-session lifecycle.

## Completed So Far

- Fortify-based authentication baseline is installed and wired.
- Custom auth Blade views are present for login, registration, password reset, email verification, password confirmation, and two-factor challenge.
- Custom RBAC foundation is implemented with `roles` and `role_user` tables.
- User-role assignment helpers, role middleware, and initial auth gates are implemented.
- Dashboard access is protected behind authentication.
- New users are assigned the `tenant` role by default.
- Pre-session model and table are implemented.
- A 15-minute pre-session token is issued for the two-factor challenge.
- Protected routes are blocked while a valid pre-session token exists.
- Invite-aware onboarding is implemented with role-scoped invitations and invitation acceptance links.
- Super Admin users can issue invitations from the protected admin area.
- Registration now requires a valid invitation token and assigns the invited role.
- User-facing 2FA settings, recovery code regeneration, and per-user auth audit logging are implemented.
- Super Admin 2FA oversight includes user status visibility, lock recovery, and admin-triggered 2FA reset actions.
- User lock state is now persisted with temporary and hard lock thresholds across login and two-factor failures.
- OTP delivery now runs through an email/WhatsApp abstraction for Super Admin and Manager roles, with resend limits and fallback-to-email behavior.
- Focused feature tests cover RBAC foundation and pre-session access blocking.
- Focused feature tests cover invitation-only registration and role-scoped invite issuance.
- Focused feature tests cover security settings and Super Admin 2FA oversight access.
- Focused feature tests cover auth lock enforcement across login and two-factor challenge routes.
- Focused feature tests cover delivered OTP challenge routing, resend limits, and delivery fallback behavior.

## Remaining Deliverables

- Backup code generation, usage, and regeneration lifecycle hardening.
- Broader Phase 1 negative-path and edge-case tests.

## Exit Criteria Status

- No protected route accessible with pre-session token: Partially complete and covered for current dashboard baseline.
- All 2FA edge cases tested: Not complete.
- Security review sign-off for auth module: Not complete.

## Current Implementation Order

1. RBAC foundation
2. Pre-session token model and protected-route enforcement
3. Invite-aware onboarding and role-controlled user creation
4. User-facing 2FA settings, recovery code lifecycle, and auth audit logging
5. OTP delivery, lock flows, and admin 2FA oversight
6. Security review and final Phase 1 closure

## Notes

- This file is intentionally marked `In Progress` because Phase 1 is not yet complete.
- When all Phase 1 deliverables and exit criteria are satisfied, this file should be updated to `Status: Completed`.
