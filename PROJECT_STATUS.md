# Project Status

## Project

AI-Based Smart Equipment Inventory and Maintenance Recommendation System Using QR Code and Android Application

## Current Phase

Phase 1 — Authentication, Users, Roles, and Permissions

## Current Branch

phase-1-authentication-and-roles

## Technical Stack

- Laravel 13
- PHP 8.3
- Filament 5
- Livewire 4
- MySQL
- Spatie Laravel Permission
- Laravel Sail
- Docker Desktop
- WSL 2
- Progressive Web Application

## Phase 0 Status

- [x] Laravel 13 installed
- [x] PHP 8.3 Sail runtime configured
- [x] MySQL configured
- [x] Filament 5 installed
- [x] Livewire 4 verified
- [x] Spatie Laravel Permission installed
- [x] Admin panel available
- [x] Administrator account created
- [x] Storage link created
- [x] Asia/Manila timezone configured
- [x] Frontend assets compiled
- [x] Automated tests passing
- [x] Git repository connected

## Phase 1 Status

- [x] User model uses Spatie roles and Filament panel authorization
- [x] Roles created: Administrator, Staff, Technician
- [x] Permissions created for user management, admin panel access, and future modules
- [x] Administrator receives every permission
- [x] Staff and Technician receive only intended non-user-management permissions
- [x] Idempotent role and permission seeder added
- [x] Existing oldest user receives Administrator on fresh installs when no Administrator exists
- [x] User policy added for user management and last-Administrator protection
- [x] Filament UserResource added for user management
- [x] Focused Phase 1 tests added

## Phase 1 Permissions

- User management: users.viewAny, users.view, users.create, users.update, users.delete, users.assignRoles
- System access: access admin panel
- Future modules: equipment.view, equipment.create, equipment.update, equipment.archive, master-data.manage, maintenance-schedules.manage, maintenance-requests.submit, maintenance-requests.review, work-orders.view, work-orders.assign, work-orders.accept, work-orders.update-assigned, work-orders.upload-evidence, work-orders.verify, beyond-repair.recommend, beyond-repair.approve, recommendations.view, recommendations.review, reports.view

## Phase 1 Tests

- Added focused tests for role and permission seeding, idempotency, role permission assignments, Filament panel access, user-management access, self-delete protection, last-Administrator protection, and permanent-delete denial for Staff and Technician.
- Results: Targeted Phase 1 tests passed; complete test suite passed.

## Next Phase

Phase 2 — Campus Locations and Equipment Categories

## Known Risks

- Shared-hosting PHP extensions must be verified before deployment.
- Image upload limits must be designed before the evidence module.
- Protected maintenance evidence must not be exposed through public URLs.
