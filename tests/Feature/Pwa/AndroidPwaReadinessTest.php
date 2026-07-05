<?php

namespace Tests\Feature\Pwa;

use App\Models\User;
use App\Services\ProductionReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AndroidPwaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manifest_contains_android_ready_fields(): void
    {
        $path = public_path('manifest.webmanifest');

        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertSame('AI Based Equipment Inventory and Maintenance', $manifest['name']);
        $this->assertSame('AI Equipment', $manifest['short_name']);
        $this->assertSame('Mobile-ready equipment inventory, maintenance work order, QR lookup, and notification workspace.', $manifest['description']);
        $this->assertSame('/admin/mobile-technician-dashboard?source=pwa', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('portrait', $manifest['orientation']);
        $this->assertSame('#f59e0b', $manifest['theme_color']);
        $this->assertSame('#f8fafc', $manifest['background_color']);
        $this->assertContains('business', $manifest['categories']);
        $this->assertContains('productivity', $manifest['categories']);
        $this->assertNotEmpty($manifest['icons']);
        $this->assertContains('any maskable', collect($manifest['icons'])->pluck('purpose')->all());
        $this->assertNotEmpty($manifest['shortcuts']);
        $this->assertSame([], $manifest['screenshots']);
    }

    public function test_service_worker_offline_and_mobile_routes_are_android_ready_without_admin_interception(): void
    {
        $serviceWorker = file_get_contents(public_path('service-worker.js'));

        $this->assertFileExists(public_path('service-worker.js'));
        $this->assertStringContainsString("'/admin'", $serviceWorker);
        $this->assertStringContainsString("'/filament'", $serviceWorker);
        $this->assertStringContainsString("'/livewire'", $serviceWorker);
        $this->assertStringContainsString("request.method !== 'GET'", $serviceWorker);
        $this->assertStringContainsString('networkFirst(request)', $serviceWorker);
        $this->assertStringContainsString("self.addEventListener('push'", $serviceWorker);
        $this->assertStringNotContainsString('OFFLINE_FALLBACK_URL', $serviceWorker);
        $this->assertStringNotContainsString('/admin/offline-queue', $serviceWorker);

        $technician = $this->userWithRole('Technician');

        $this->get('/offline')->assertOk()->assertSee('Offline Mode');

        $this->actingAs($technician)
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('Mobile technician workspace');

        $this->actingAs($technician)
            ->get('/admin/offline-queue')
            ->assertNotFound();

        $this->actingAs($technician)
            ->get(route('equipment.scan'))
            ->assertOk()
            ->assertSee('Camera scanning requires HTTPS or localhost');
    }

    public function test_twa_assetlinks_template_and_android_documentation_exist(): void
    {
        $templatePath = public_path('.well-known/assetlinks.template.json');
        $documentationPath = base_path('docs/ANDROID_TWA_PREPARATION.md');
        $packagingDocumentationPath = base_path('docs/ANDROID_PACKAGING_TWA.md');

        $this->assertFileExists($templatePath);
        $this->assertFileExists($documentationPath);
        $this->assertFileExists($packagingDocumentationPath);

        $template = file_get_contents($templatePath);
        $documentation = file_get_contents($documentationPath);
        $packagingDocumentation = file_get_contents($packagingDocumentationPath);

        $this->assertStringContainsString('delegate_permission/common.handle_all_urls', $template);
        $this->assertStringContainsString('edu.snsu.delcarmen.cfs.cmms', $template);
        $this->assertStringContainsString('REPLACE_WITH_RELEASE_CERTIFICATE_SHA256_FINGERPRINT', $template);
        $this->assertStringContainsString('Why Trusted Web Activity Is Recommended', $documentation);
        $this->assertStringContainsString('Capacitor Alternative', $documentation);
        $this->assertStringContainsString('QR Scanner Android Compatibility Notes', $documentation);
        $this->assertStringContainsString('Online-only Mobile Dashboard Android Notes', $documentation);
        $this->assertStringContainsString('Trusted Web Activity', $packagingDocumentation);
        $this->assertStringContainsString('Bubblewrap', $packagingDocumentation);
        $this->assertStringContainsString('assetlinks.json', $packagingDocumentation);
        $this->assertStringContainsString('APK', $packagingDocumentation);
        $this->assertStringContainsString('AAB', $packagingDocumentation);
        $this->assertStringContainsString('edu.snsu.delcarmen.cfs.cmms', $packagingDocumentation);
    }

    public function test_production_readiness_service_includes_android_readiness_items(): void
    {
        $items = app(ProductionReadinessService::class)->getAndroidReadinessChecklist();
        $titles = collect($items)->pluck('title')->all();

        $this->assertContains('PWA manifest exists', $titles);
        $this->assertContains('Service worker exists', $titles);
        $this->assertContains('Offline page exists', $titles);
        $this->assertContains('Mobile dashboard exists', $titles);
        $this->assertContains('QR scanner route exists', $titles);
        $this->assertNotContains('Offline queue route exists', $titles);
        $this->assertContains('HTTPS required for camera', $titles);
        $this->assertContains('TWA assetlinks template prepared', $titles);
        $this->assertContains('Android documentation prepared', $titles);
    }

    public function test_production_readiness_service_includes_android_packaging_items(): void
    {
        $items = app(ProductionReadinessService::class)->getAndroidPackagingChecklist();
        $titles = collect($items)->pluck('title')->all();

        $this->assertContains('Phase 15A completed', $titles);
        $this->assertContains('Android TWA documentation exists', $titles);
        $this->assertContains('Android packaging documentation exists', $titles);
        $this->assertContains('Assetlinks template exists', $titles);
        $this->assertContains('Production HTTPS domain required', $titles);
        $this->assertContains('Real SHA-256 fingerprint required', $titles);
        $this->assertContains('APK/AAB generation deferred', $titles);
        $this->assertContains('Real Android device testing required', $titles);
    }

    public function test_android_phase_15c_bootstrap_files_exist(): void
    {
        $readmePath = base_path('android/README.md');
        $bubblewrapPath = base_path('android/bubblewrap.config.template.json');
        $signingGuidePath = base_path('android/SIGNING_GUIDE.md');
        $releaseChecklistPath = base_path('android/RELEASE_CHECKLIST.md');

        $this->assertFileExists($readmePath);
        $this->assertFileExists($bubblewrapPath);
        $this->assertFileExists($signingGuidePath);
        $this->assertFileExists($releaseChecklistPath);

        $this->assertStringContainsString('Trusted Web Activity', file_get_contents($readmePath));
        $this->assertStringContainsString('Minimum SDK', file_get_contents($readmePath));
        $this->assertStringContainsString('Android Studio', file_get_contents($readmePath));

        $bubblewrap = json_decode(file_get_contents($bubblewrapPath), true);

        $this->assertSame('REPLACE_WITH_APPLICATION_ID', $bubblewrap['applicationId']);
        $this->assertSame('REPLACE_WITH_PRODUCTION_HOST', $bubblewrap['host']);
        $this->assertSame('REPLACE_WITH_LAUNCHER_NAME', $bubblewrap['launcherName']);
        $this->assertSame('REPLACE_WITH_THEME_COLOR', $bubblewrap['themeColor']);
        $this->assertSame('REPLACE_WITH_BACKGROUND_COLOR', $bubblewrap['backgroundColor']);
        $this->assertSame('REPLACE_WITH_START_URL', $bubblewrap['startUrl']);
        $this->assertSame('REPLACE_WITH_DISPLAY_MODE', $bubblewrap['display']);
        $this->assertSame('REPLACE_WITH_NAVIGATION_COLOR', $bubblewrap['navigationColor']);
        $this->assertSame('REPLACE_WITH_SIGNING_KEY_PATH', $bubblewrap['signingKeyPath']);
        $this->assertSame('REPLACE_WITH_SIGNING_KEY_ALIAS', $bubblewrap['signingKeyAlias']);

        $signingGuide = file_get_contents($signingGuidePath);
        $this->assertStringContainsString('Debug Keystore', $signingGuide);
        $this->assertStringContainsString('Release Keystore', $signingGuide);
        $this->assertStringContainsString('SHA-256 Fingerprint Generation', $signingGuide);
        $this->assertStringContainsString('keytool', $signingGuide);

        $releaseChecklist = file_get_contents($releaseChecklistPath);
        $this->assertStringContainsString('HTTPS verification', $releaseChecklist);
        $this->assertStringContainsString('Digital Asset Links verification', $releaseChecklist);
        $this->assertStringContainsString('APK build', $releaseChecklist);
        $this->assertStringContainsString('AAB build', $releaseChecklist);
        $this->assertStringContainsString('QR scanner testing', $releaseChecklist);
        $this->assertStringContainsString('Maintenance request workflow testing', $releaseChecklist);
    }

    public function test_production_readiness_service_includes_android_release_items(): void
    {
        $items = app(ProductionReadinessService::class)->getAndroidReleaseChecklist();
        $titles = collect($items)->pluck('title')->all();

        $this->assertContains('HTTPS domain available', $titles);
        $this->assertContains('Asset Links deployed', $titles);
        $this->assertContains('PWA validated', $titles);
        $this->assertContains('Service Worker active', $titles);
        $this->assertContains('Online mobile dashboard verified', $titles);
        $this->assertNotContains('Offline queue verified', $titles);
        $this->assertContains('QR scanner tested', $titles);
        $this->assertContains('Android documentation complete', $titles);
        $this->assertContains('Signing key prepared', $titles);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
