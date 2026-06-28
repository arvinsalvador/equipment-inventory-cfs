# Project Status

## Project

AI-Based Smart Equipment Inventory and Maintenance Recommendation System Using QR Code and Android Application

## Current Phase

Phase 2 — Campus Locations and Equipment Categories

## Current Branch

dev

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

## Phase 2 Status

- [x] Locations table and model added
- [x] Equipment categories table and model added
- [x] Default campus location hierarchy seeded
- [x] Default equipment categories seeded
- [x] Location and EquipmentCategory policies added
- [x] Filament LocationResource added
- [x] Filament EquipmentCategoryResource added
- [x] Records can be deactivated with Active status fields
- [x] Staff and Technician cannot manage master data
- [x] Focused Phase 2 tests added and passing

## Phase 2 Default Locations

- SNSU Del Carmen Campus
- Climate Field School Building under SNSU Del Carmen Campus
- Climate Field School Main Room under Climate Field School Building
- Climate Field School Storage Area under Climate Field School Building
- Other Campus Location under SNSU Del Carmen Campus

## Phase 2 Default Equipment Categories

- Weather monitoring equipment
- Agricultural equipment
- Laboratory equipment
- Computers
- Air-conditioning units
- Office equipment
- Water systems
- Other equipment

## Phase 2 Policies

- LocationPolicy
- EquipmentCategoryPolicy

## Phase 2 Filament Resources

- LocationResource under Master Data
- EquipmentCategoryResource under Master Data

## Phase 2 Tests

- Added focused tests for default master-data seeding, seeder idempotency, location parent-child relationships, active scopes, master-data authorization, active data viewing, and Filament resource access.
- Results: Targeted Phase 2 tests passed; complete test suite passed.

## Next Phase

Phase 3 — Equipment Inventory

## Known Risks

- Shared-hosting PHP extensions must be verified before deployment.
- Image upload limits must be designed before the evidence module.
- Protected maintenance evidence must not be exposed through public URLs.
