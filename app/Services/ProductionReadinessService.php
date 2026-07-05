<?php

namespace App\Services;

use App\Filament\Pages\ExecutiveDecisionSupport;
use App\Filament\Pages\MobileTechnicianDashboard;
use App\Filament\Pages\SystemConfiguration;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\AuditLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class ProductionReadinessService
{
    /**
     * @return array<int, array<string, string>>
     */
    public function getSecurityChecklist(): array
    {
        return [
            $this->item('APP_DEBUG disabled', config('app.debug') ? 'warning' : 'ready', 'APP_DEBUG should be false in production.', 'Set APP_DEBUG=false before launch.'),
            $this->item('HTTPS enforced', request()->isSecure() ? 'ready' : 'warning', 'Production traffic should be served over HTTPS.', 'Install SSL and redirect HTTP to HTTPS on the hosting panel.'),
            $this->item('Authorization policies present', class_exists(EquipmentResource::class) && class_exists(WorkOrderResource::class) ? 'ready' : 'warning', 'Sensitive equipment and work-order pages are protected by authorization rules.', 'Review policies before publishing the admin panel.'),
            $this->item('Audit trail available', class_exists(AuditLog::class) && class_exists(AuditLogResource::class) ? 'ready' : 'warning', 'Audit logging should remain available for administrative changes.', 'Verify audit log retention and administrator access.'),
            $this->item('Admin routes authenticated', Route::has('filament.admin.auth.login') ? 'ready' : 'warning', 'Filament admin routes should require authentication.', 'Confirm unauthenticated users cannot access /admin pages.'),
            $this->item('Evidence upload validation', class_exists(WorkOrderResource::class) ? 'ready' : 'review', 'Work-order evidence uploads should keep type and size validation enabled.', 'Test evidence upload limits after deployment.'),
            $this->item('System settings restricted', class_exists(SystemConfiguration::class) ? 'ready' : 'warning', 'System settings must remain admin-only.', 'Keep system-settings permissions assigned only to administrators.'),
            $this->item('Executive dashboard restricted', class_exists(ExecutiveDecisionSupport::class) ? 'ready' : 'warning', 'Executive analytics should require the executive dashboard permission.', 'Verify staff and technician roles cannot access executive reports.'),
            $this->item('Public QR lookup safety', Route::has('equipment.lookup') ? 'ready' : 'warning', 'QR lookup should expose operational equipment information only to authorized users.', 'Recheck lookup permissions and returned fields before launch.'),
            $this->item('CSRF protection enabled', 'ready', 'State-changing web requests should keep Laravel CSRF protection enabled.', 'Do not disable CSRF middleware for admin forms.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getPerformanceChecklist(): array
    {
        return [
            $this->item('Config cache ready', 'review', 'Production should run php artisan config:cache after environment setup.', 'Cache configuration after final .env values are set.'),
            $this->item('Route cache ready', 'review', 'Production should run php artisan route:cache when routes are stable.', 'Clear and rebuild route cache during deployment.'),
            $this->item('View cache ready', 'review', 'Blade views should be cached for shared hosting performance.', 'Run php artisan view:cache after deployment.'),
            $this->item('Query optimization reviewed', 'review', 'Large reports and dashboards should avoid avoidable N+1 queries.', 'Review slow query logs after real data import.'),
            $this->item('Reports paginated', 'ready', 'Operational reports should remain paginated or filtered for large data sets.', 'Verify report filters before importing production data.'),
            $this->item('Dashboard query load reviewed', 'review', 'Dashboard widgets should avoid expensive repeated aggregation under high traffic.', 'Monitor admin dashboard response time after launch.'),
            $this->item('Upload size planned', 'review', 'Photo and evidence upload sizes must fit PHP and hosting limits.', 'Align upload_max_filesize and post_max_size with application limits.'),
            $this->item('Database indexes reviewed', 'review', 'Common filters such as equipment code, status, location, and dates should be indexed as data grows.', 'Review MySQL indexes after production-sized testing.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getDeploymentChecklist(): array
    {
        return [
            $this->item('Upload project files', 'review', 'Upload application files outside public web root where hosting allows.', 'Keep only the public directory exposed as document root.'),
            $this->item('Document root set to public', 'critical', 'The domain document root must point to the Laravel public directory.', 'Configure shared hosting domain root to public.'),
            $this->item('Database configured', 'review', 'MySQL credentials must be configured before migrations.', 'Create the database and user in the hosting control panel.'),
            $this->item('Migrations ready', 'review', 'Database migrations must run once on the production database.', 'Run php artisan migrate --force.'),
            $this->item('Seeders ready', 'review', 'Roles, permissions, and default settings must be seeded.', 'Run php artisan db:seed --force after migration.'),
            $this->item('Storage link created', File::exists(public_path('storage')) ? 'ready' : 'warning', 'Public uploads require the storage symlink.', 'Run php artisan storage:link or create the hosting equivalent.'),
            $this->item('File permissions set', 'review', 'Laravel must write to storage and cache directories.', 'Set writable permissions for storage and bootstrap/cache.'),
            $this->item('Mail configured', 'review', 'Mail settings must be verified before notifications are enabled.', 'Send a test notification from production.'),
            $this->item('Queue configured', 'review', 'Queue settings should match the hosting worker strategy.', 'Use database or sync queue based on hosting capabilities.'),
            $this->item('Scheduler cron configured', 'review', 'Laravel scheduler should run every minute.', 'Add the scheduler cron entry in the hosting panel.'),
            $this->item('QR lookup verified', Route::has('equipment.lookup') ? 'ready' : 'warning', 'QR code lookup routes must work after deployment.', 'Scan a generated QR code on a mobile device.'),
            $this->item('Admin login verified', Route::has('filament.admin.auth.login') ? 'ready' : 'warning', 'Filament login must be reachable after deployment.', 'Visit /admin/login after cache rebuild.'),
            $this->item('PWA verified', 'review', 'Manifest, service worker, and offline page should be verified in the browser.', 'Check install prompt and offline fallback after deployment.'),
            $this->item('Evidence upload verified', class_exists(WorkOrderResource::class) ? 'ready' : 'warning', 'Work-order evidence uploads should work with production storage.', 'Upload and view sample evidence after deployment.'),
            $this->item('Reports and exports verified', 'review', 'Reports should render correctly with production permissions.', 'Open inventory, lifecycle, and executive reports as administrator.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getBackupChecklist(): array
    {
        return [
            $this->item('Database backup planned', 'critical', 'MySQL backups should be scheduled before launch.', 'Create a daily backup plan with retention.'),
            $this->item('Uploaded evidence backup planned', 'critical', 'Uploaded evidence and photos must be backed up with the database.', 'Include storage/app/public and protected evidence folders.'),
            $this->item('QR code assets backup planned', 'review', 'Generated QR assets should be included in storage backups.', 'Back up equipment/qr-codes with other uploaded assets.'),
            $this->item('System settings backup planned', 'review', 'System settings are operational configuration and should be restorable.', 'Include system settings tables in database backups.'),
            $this->item('Audit logs backup planned', 'review', 'Audit logs should be preserved according to retention policy.', 'Include audit tables in backup and archive planning.'),
            $this->item('Backup frequency documented', 'review', 'Backup frequency should match operational data changes.', 'Use daily database backups and regular file backups at minimum.'),
            $this->item('Restore test scheduled', 'critical', 'Backups are not complete until restore is tested.', 'Perform a test restore before go-live.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getQueueChecklist(): array
    {
        return [
            $this->item('Scheduler cron ready', 'review', 'Laravel scheduler needs a hosting cron entry.', 'Run php artisan schedule:run every minute.'),
            $this->item('Email queue strategy ready', 'review', 'Queued email notifications require a configured queue worker.', 'Choose sync, database queue, or hosting-supported worker.'),
            $this->item('Failed jobs monitored', 'review', 'Failed queued jobs should be reviewed regularly.', 'Add failed job review to administrator operating procedures.'),
            $this->item('Browser push preparation only', 'ready', 'Browser push is prepared but should be verified separately per browser.', 'Treat push notifications as optional until production testing is complete.'),
            $this->item('Notification defaults configured', 'review', 'Notification preferences and defaults should be seeded.', 'Run seeders and review notification defaults.'),
            $this->item('Maintenance reminders ready', 'review', 'Maintenance reminders depend on scheduler and mail readiness.', 'Test a reminder flow after scheduler is active.'),
            $this->item('Work order notifications ready', 'review', 'Work-order notifications depend on mail and queue settings.', 'Test assignment and verification notifications.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getStorageChecklist(): array
    {
        return [
            $this->item('Public disk configured', config('filesystems.disks.public.driver') === 'local' ? 'ready' : 'review', 'The public disk should be configured for uploaded photos and QR codes.', 'Verify public disk root and URL on shared hosting.'),
            $this->item('Storage link available', File::exists(public_path('storage')) ? 'ready' : 'warning', 'The public/storage link must expose public uploaded files.', 'Run php artisan storage:link.'),
            $this->item('Equipment photos readable', 'review', 'Equipment photos should load from /storage paths.', 'Open an equipment list and view page after deployment.'),
            $this->item('QR code assets readable', 'review', 'Generated QR codes should load from /storage paths.', 'Generate and scan a sample QR code.'),
            $this->item('Protected evidence reviewed', 'review', 'Sensitive maintenance evidence should not be exposed through public URLs.', 'Verify evidence storage and policies before launch.'),
            $this->item('Upload permissions verified', 'critical', 'The web server must write uploaded files safely.', 'Set writable storage permissions without using unsafe broad permissions.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getPwaChecklist(): array
    {
        return [
            $this->item('Manifest exists', File::exists(public_path('manifest.webmanifest')) ? 'ready' : 'warning', 'The PWA manifest should be available to browsers.', 'Verify /manifest.webmanifest loads.'),
            $this->item('Service worker exists', File::exists(public_path('service-worker.js')) ? 'ready' : 'warning', 'The service worker should be available for supported browsers.', 'Verify /service-worker.js loads.'),
            $this->item('Offline page route exists', Route::has('pwa.offline') ? 'ready' : 'warning', 'The offline page should be routable.', 'Open /offline before launch.'),
            $this->item('Admin routes excluded from offline shell', 'critical', 'Service worker should exclude admin, Filament, and Livewire routes.', 'Confirm /admin never falls back to the offline shell.'),
            $this->item('Filament routes excluded from offline shell', 'critical', 'Filament assets and Livewire calls should not be cached as offline pages.', 'Verify /filament and /livewire requests go to network.'),
            $this->item('Online-only mobile dashboard accessible', class_exists(MobileTechnicianDashboard::class) ? 'ready' : 'warning', 'The mobile technician dashboard should remain accessible to intended users without offline queue widgets.', 'Open the mobile dashboard on a phone-sized viewport.'),
            $this->item('Offline synchronization deferred', ! File::exists(public_path('offline-sync.js')) ? 'ready' : 'warning', 'Offline queue replay is intentionally disabled in the online-only PWA.', 'Do not re-enable offline sync until a redesigned version is validated.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getAndroidReadinessChecklist(): array
    {
        return [
            $this->item('PWA manifest exists', File::exists(public_path('manifest.webmanifest')) ? 'ready' : 'warning', 'Android packaging requires a valid web app manifest.', 'Verify /manifest.webmanifest loads with Android-ready metadata.'),
            $this->item('Service worker exists', File::exists(public_path('service-worker.js')) ? 'ready' : 'warning', 'TWA installability expects a service worker for the PWA.', 'Verify /service-worker.js is available and does not intercept admin routes.'),
            $this->item('Offline page exists', Route::has('pwa.offline') ? 'ready' : 'warning', 'The standalone offline notice page should remain available, without offline queue synchronization.', 'Open /offline before Android packaging.'),
            $this->item('Mobile dashboard exists', class_exists(MobileTechnicianDashboard::class) ? 'ready' : 'warning', 'The mobile technician dashboard is the intended Android app entry point.', 'Use /admin/mobile-technician-dashboard as the TWA start URL.'),
            $this->item('QR scanner route exists', Route::has('equipment.scan') ? 'ready' : 'warning', 'Android users need QR scanning with manual lookup fallback.', 'Test /equipment/scan on Chrome Android over HTTPS.'),
            $this->item('HTTPS required for camera', request()->isSecure() ? 'ready' : 'critical', 'Android camera access requires HTTPS except localhost development.', 'Deploy with SSL before QR scanner device testing.'),
            $this->item('TWA assetlinks template prepared', File::exists(public_path('.well-known/assetlinks.template.json')) ? 'ready' : 'warning', 'Trusted Web Activity verification needs Digital Asset Links.', 'Create real /.well-known/assetlinks.json after the release signing fingerprint is known.'),
            $this->item('Android documentation prepared', File::exists(base_path('docs/ANDROID_TWA_PREPARATION.md')) ? 'ready' : 'warning', 'Android packaging preparation should be documented before APK/AAB generation.', 'Review the TWA preparation guide before Phase 15B.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getAndroidPackagingChecklist(): array
    {
        $projectStatus = File::exists(base_path('PROJECT_STATUS.md'))
            ? File::get(base_path('PROJECT_STATUS.md'))
            : '';

        return [
            $this->item('Phase 15A completed', str_contains($projectStatus, 'Phase 15A status: Complete') ? 'ready' : 'warning', 'Android packaging should start only after Phase 15A readiness is complete.', 'Confirm PROJECT_STATUS.md lists Phase 15A as complete.'),
            $this->item('Android TWA documentation exists', File::exists(base_path('docs/ANDROID_TWA_PREPARATION.md')) ? 'ready' : 'warning', 'TWA preparation documentation should remain available for package generation.', 'Review docs/ANDROID_TWA_PREPARATION.md before generating the Android project.'),
            $this->item('Android packaging documentation exists', File::exists(base_path('docs/ANDROID_PACKAGING_TWA.md')) ? 'ready' : 'warning', 'Phase 15B packaging documentation should guide Bubblewrap APK and AAB generation.', 'Review docs/ANDROID_PACKAGING_TWA.md on the developer machine.'),
            $this->item('Assetlinks template exists', File::exists(public_path('.well-known/assetlinks.template.json')) ? 'ready' : 'warning', 'Digital Asset Links should start from a placeholder template, not fake production values.', 'Replace placeholders only when package name and SHA-256 fingerprint are final.'),
            $this->item('Production HTTPS domain required', request()->isSecure() ? 'ready' : 'critical', 'TWA generation should target the final production HTTPS domain.', 'Do not initialize Bubblewrap from localhost or a temporary URL.'),
            $this->item('Real SHA-256 fingerprint required', 'critical', 'The final assetlinks.json requires the release signing certificate SHA-256 fingerprint.', 'Generate the fingerprint from the release signing key before finalizing Digital Asset Links.'),
            $this->item('APK/AAB generation deferred', 'review', 'APK and AAB generation remains deferred until the production domain and signing values are available.', 'Generate APK/AAB in Phase 15C or release preparation after production verification.'),
            $this->item('Real Android device testing required', 'critical', 'TWA behavior, camera permissions, QR scanning, uploads, and the online dashboard must be tested on real Android devices.', 'Test Chrome Android and the generated TWA before release.'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getAndroidReleaseChecklist(): array
    {
        return [
            $this->item('HTTPS domain available', request()->isSecure() ? 'ready' : 'critical', 'The Android release build must target the final HTTPS production domain.', 'Confirm the production domain has valid SSL before APK/AAB generation.'),
            $this->item('Asset Links deployed', File::exists(public_path('.well-known/assetlinks.json')) ? 'ready' : 'critical', 'Trusted Web Activity release verification requires the final assetlinks.json file.', 'Deploy assetlinks.json with the real package name and release SHA-256 fingerprint.'),
            $this->item('PWA validated', File::exists(public_path('manifest.webmanifest')) && File::exists(public_path('pwa.js')) ? 'ready' : 'warning', 'The production PWA manifest and install scripts should be valid before Android generation.', 'Validate the production manifest and install prompt behavior in Chrome.'),
            $this->item('Service Worker active', File::exists(public_path('service-worker.js')) ? 'ready' : 'warning', 'The Android TWA depends on the existing service worker for safe asset caching.', 'Verify service-worker registration on the production domain.'),
            $this->item('Online mobile dashboard verified', class_exists(MobileTechnicianDashboard::class) ? 'review' : 'warning', 'The packaged app should open the online technician dashboard and load live data.', 'Validate dashboard, QR scanning, uploads, and maintenance workflows on a physical Android device.'),
            $this->item('QR scanner tested', Route::has('equipment.scan') ? 'review' : 'warning', 'QR scanning must be tested on Chrome Android and the generated TWA over HTTPS.', 'Complete camera permission and QR scan validation on real Android hardware.'),
            $this->item('Android documentation complete', File::exists(base_path('android/README.md')) && File::exists(base_path('android/SIGNING_GUIDE.md')) && File::exists(base_path('android/RELEASE_CHECKLIST.md')) ? 'ready' : 'warning', 'Android bootstrap, signing, and release documentation should be present before build generation.', 'Review the android/ documentation before Phase 15D.'),
            $this->item('Signing key prepared', 'critical', 'A release signing key is required for real APK/AAB generation and Digital Asset Links verification.', 'Create and secure the release keystore outside this repository before release builds.'),
        ];
    }

    public function getOverallReadinessScore(): int
    {
        $items = collect([
            ...$this->getSecurityChecklist(),
            ...$this->getPerformanceChecklist(),
            ...$this->getDeploymentChecklist(),
            ...$this->getBackupChecklist(),
            ...$this->getQueueChecklist(),
            ...$this->getStorageChecklist(),
            ...$this->getPwaChecklist(),
            ...$this->getAndroidReadinessChecklist(),
            ...$this->getAndroidPackagingChecklist(),
            ...$this->getAndroidReleaseChecklist(),
        ]);

        if ($items->isEmpty()) {
            return 0;
        }

        $readyWeight = $items->sum(fn (array $item): int => match ($item['status']) {
            'ready' => 2,
            'review' => 1,
            default => 0,
        });

        return (int) round(($readyWeight / ($items->count() * 2)) * 100);
    }

    /**
     * @return array<int, string>
     */
    public function getProductionWarnings(): array
    {
        return [
            'Production readiness checks are advisory and do not replace manual server verification.',
            'APP_DEBUG, mail, queue, scheduler, and database credentials must be verified on the live host without exposing secrets.',
            'Shared-hosting public storage symlink and file permissions must be tested with real uploads.',
            'Service worker behavior should be validated in a fresh browser profile after deployment.',
            'Android TWA packaging still requires HTTPS, real Digital Asset Links, release signing, and device testing.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getRecommendedActions(): array
    {
        return [
            'Run migrations and seeders on a staging copy before production launch.',
            'Create and test a database plus uploaded-file restore before accepting live data.',
            'Enable Laravel config, route, and view caches after final production configuration.',
            'Verify admin login, QR lookup, PWA install prompt, evidence upload, reports, and notifications after deployment.',
            'Document who is responsible for daily backups, failed jobs, and audit log review.',
            'Prepare real Android launcher assets and Digital Asset Links before generating an APK or AAB.',
        ];
    }

    private function item(string $title, string $status, string $description, string $action): array
    {
        return [
            'title' => $title,
            'status' => $status,
            'description' => $description,
            'action' => $action,
        ];
    }
}
