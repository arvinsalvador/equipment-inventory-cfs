# Production Deployment Guide

## Overview

This guide prepares the AI-Based Smart Equipment Inventory and Maintenance Recommendation System for shared-hosting production deployment. It assumes the existing Laravel, Filament, MySQL, PWA, QR code, notification, audit, and rule-based recommendation architecture remains unchanged.

## Server Requirements

- PHP version compatible with the application.
- Required PHP extensions for Laravel, MySQL, file uploads, image handling, sessions, and mail.
- MySQL database and database user.
- Composer available locally or on the server.
- Web server document root that can point to the Laravel `public` directory.
- Cron support for the Laravel scheduler.
- Writable `storage` and `bootstrap/cache` directories.

## Shared Hosting Deployment Steps

1. Back up the existing site and database if replacing a live deployment.
2. Upload application files outside the public web root when the host supports it.
3. Point the domain document root to the application `public` directory.
4. Configure production environment values on the host.
5. Install Composer dependencies with production options.
6. Run database migrations with `php artisan migrate --force`.
7. Run seeders with `php artisan db:seed --force`.
8. Create the storage link with `php artisan storage:link`.
9. Build Laravel caches after final configuration.
10. Verify admin login, QR lookup, PWA assets, uploads, notifications, and reports.

## Database Setup

- Create a MySQL database and least-privilege database user.
- Import data only after a verified backup exists.
- Run migrations once against the production database.
- Run seeders to ensure roles, permissions, default settings, and sample-free required defaults exist.

## File Permissions

- `storage` must be writable by the web server.
- `bootstrap/cache` must be writable by the web server.
- Avoid broad unsafe permissions such as globally writable directories when hosting supports safer ownership settings.
- Confirm uploaded equipment photos, QR codes, and evidence files can be written and read by the application.

## Storage Link

Run:

```bash
php artisan storage:link
```

On shared hosting without symlink support, create the host-supported equivalent so `public/storage` exposes `storage/app/public`.

## Queue Setup

- Use `sync` only when background processing is unavailable and email volume is low.
- Use the database queue when the host supports a persistent worker or scheduled queue processing.
- Monitor failed jobs if queued notifications are enabled.
- Test work-order and maintenance notification flows after deployment.

## Scheduler Cron Setup

Add a cron entry similar to:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Use the absolute project path from the hosting account.

## Mail Setup

- Configure SMTP or the hosting mail service.
- Send a test notification before launch.
- Confirm sender address, reply-to behavior, and spam handling.
- Verify queued mail behavior if queues are enabled.

## PWA Verification

- Open `/manifest.webmanifest`.
- Open `/service-worker.js`.
- Open `/offline`.
- Confirm `/admin`, `/filament`, and `/livewire` routes are not replaced by the offline shell.
- Verify the install prompt where browser support allows it.
- Confirm offline sync UI appears only on intended mobile/offline pages.

## QR Code Verification

- Generate a QR code for a test equipment record.
- Confirm the database path is relative to the public disk, such as `equipment/qr-codes/file.svg`.
- Confirm the displayed URL uses `/storage/equipment/qr-codes/...`.
- Scan the QR code on a mobile device.
- Confirm the public lookup page shows the equipment photo and QR image.

## Backup Checklist

- Schedule database backups.
- Back up uploaded equipment photos.
- Back up QR code assets.
- Back up work-order evidence.
- Back up system settings and audit logs through the database backup.
- Store backups away from the production hosting account when possible.
- Document backup ownership and retention.

## Restore Checklist

- Restore the database to a staging database.
- Restore uploaded files to a staging storage directory.
- Recreate the storage link.
- Clear and rebuild caches.
- Verify admin login, equipment records, QR lookup, reports, notifications, and audit logs.
- Record restore duration and issues.

## Post-Deployment Checklist

- Set debug mode off.
- Confirm HTTPS is active.
- Run migrations and seeders.
- Build config, route, and view caches.
- Verify administrator access.
- Verify Staff and Technician access boundaries.
- Verify system settings are administrator-only.
- Verify QR lookup and scanner pages.
- Verify PWA install and offline behavior.
- Verify equipment photo, QR code, and evidence uploads.
- Verify scheduled tasks and notifications.
- Verify reports and executive dashboard.

## Troubleshooting

- If admin pages show an offline shell, clear browser service worker data and verify service worker route exclusions.
- If uploaded images do not show, verify `public/storage`, file existence, and web server permissions.
- If QR codes generate but do not display, verify the saved path is relative to the public disk and the URL starts with `/storage/`.
- If notifications do not send, verify mail credentials, queue driver, failed jobs, and scheduler.
- If pages are stale after deployment, clear and rebuild Laravel caches.

## Rollback Guidance

- Keep a copy of the previous release files.
- Keep a pre-deployment database backup.
- Stop queue workers or scheduled jobs during rollback when possible.
- Restore files and database together when schema changes were deployed.
- Rebuild caches after rollback.
- Verify admin login, equipment lookup, uploads, and notifications before reopening the system.
