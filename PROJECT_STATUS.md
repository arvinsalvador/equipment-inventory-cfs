# Project Status

## Project

AI-Based Smart Equipment Inventory and Maintenance Recommendation System Using QR Code and Android Application

## Current Phase

Phase 3B — Equipment Filament Resource

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

## Phase 3A Status

- [x] Equipment table added with archive fields
- [x] Equipment model added with relationships, casts, condition options, operational status options, active scope, and archived scope
- [x] Equipment location history table added
- [x] EquipmentLocationHistory model added with transfer relationships
- [x] EquipmentPolicy added for view, create, update, archive, and permanent-delete denial
- [x] Optional sample equipment seeder added
- [x] Focused Phase 3A tests added and passing

## Phase 3A Equipment Foundation

- Equipment records link to equipment categories and current locations
- Equipment archive fields: is_archived, archived_at, archived_by
- Location history records movement from one location to another
- Permanent delete is denied by policy for this MVP

## Phase 3A Sample Equipment Seeder

- Seeds EQ-CFS-0001 Automatic Weather Station Console
- Seeds EQ-CFS-0002 Training Laptop
- Seeds EQ-CFS-0003 Office Printer
- Skips safely when required Phase 2 master data is missing

## Phase 3A Tests

- Added focused tests for equipment persistence, unique equipment codes, relationships, archiving, scopes, location history, policy permissions, and sample seeder idempotency.
- Results: Targeted Phase 3A tests passed; complete test suite passed.

## Phase 3B Status

- [x] EquipmentResource added under Inventory Management
- [x] Equipment list, create, view, and edit pages added
- [x] Equipment table columns, sorting, search, and filters added
- [x] Equipment form added with required validation and active category/location selects
- [x] Archive action added with confirmation and archive metadata updates
- [x] Permanent delete is not exposed as a normal action
- [x] Policy-backed Filament authorization added
- [x] Focused Phase 3B tests added and passing

## Phase 3B EquipmentResource

- Navigation group: Inventory Management
- Label: Equipment
- Pages: List, Create, View, Edit
- Actions: View, Edit, Archive when authorized and not already archived

## Phase 3B Table, Search, And Filters

- Columns include equipment code, property number, equipment name, category, current location, condition, operational status, next maintenance date, archived status, created date, and updated date.
- Search covers equipment code, property number, equipment name, brand, model, serial number, and custodian.
- Filters cover category, current location, condition, operational status, and archived status.

## Phase 3B Form And Archive Rules

- Equipment code, equipment name, category, current location, condition, and operational status are required.
- Equipment code is unique with edit-record exclusion.
- Acquisition cost must be numeric and non-negative.
- Staff cannot mark equipment archived during create or edit.
- Archive action sets is_archived, archived_at, and archived_by.

## Phase 3B Tests

- Added focused tests for Filament page access, create/edit authorization, form validation, Staff creation rules, Technician restrictions, archive action behavior, and archive persistence.
- Results: Targeted Phase 3B tests passed; complete test suite passed.

## Next Phase

Phase 3C — Equipment Photos and Location Transfer History

## Known Risks

- Shared-hosting PHP extensions must be verified before deployment.
- Image upload limits must be designed before the evidence module.
- Protected maintenance evidence must not be exposed through public URLs.
