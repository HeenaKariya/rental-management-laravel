# Phase 0 Decisions

Date: 2026-04-28

## Confirmed Stack

- Framework: Laravel 11
- PHP target: 8.2+
- Database: MySQL 8
- Auth: Laravel Fortify + Sanctum
- UI: Blade + Livewire + Alpine
- Testing: PHPUnit
- RBAC strategy: custom roles and permissions model
- Queue driver baseline: database
- Cache driver baseline: database for app bootstrap, Redis available in local stack for later adoption
- File driver baseline: local filesystem in development

## Module Boundary Convention

- Business modules live under `app/Domain/<Module>/`
- Initial modules are `Auth`, `Property`, `Tenancy`, `Finance`, `Agreement`, and `Shared`

## Phase 0 Notes

- Fortify is installed with custom Blade view endpoints so auth routes are valid without adopting a starter kit layout.
- Sanctum is installed at the package level and ready for API/auth token work in Phase 1.
- Livewire and Alpine are installed to lock the UI stack early, even though Phase 0 keeps UI interaction intentionally light.
- Docker-backed local services are available for MySQL, Redis, Mailpit, and phpMyAdmin.
