<?php

namespace Tests\Feature\Notifications;

use App\Filament\Pages\BrowserPushDevices;
use App\Filament\Pages\NotificationPreferences;
use App\Models\BrowserPushSubscription;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\BrowserPushPreparationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrowserPushPreparationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->staff = $this->userWithRole('Staff');
        $this->administrator = $this->userWithRole('Administrator');
    }

    public function test_browser_push_subscription_model_relationship_scopes_and_helpers_work(): void
    {
        $active = BrowserPushSubscription::create([
            'user_id' => $this->staff->id,
            'endpoint' => 'https://push.example.test/active',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Workstation',
        ]);
        $revoked = BrowserPushSubscription::create([
            'user_id' => $this->staff->id,
            'endpoint' => 'https://push.example.test/revoked',
            'is_active' => false,
            'revoked_at' => now(),
        ]);

        $active = $active->refresh();

        $this->assertTrue($active->user->is($this->staff));
        $this->assertTrue($active->isActive());
        $this->assertTrue($revoked->isRevoked());
        $this->assertSame(1, BrowserPushSubscription::active()->count());
        $this->assertSame(1, BrowserPushSubscription::revoked()->count());
        $this->assertSame(2, BrowserPushSubscription::forUser($this->staff)->count());
        $this->assertTrue($this->staff->fresh()->browserPushSubscriptions->contains($active));
        $this->assertTrue($this->staff->fresh()->activeBrowserPushSubscriptions->contains($active));

        $active->markSeen();
        $this->assertNotNull($active->fresh()->last_seen_at);

        $active->revoke();
        $this->assertFalse($active->fresh()->is_active);
        $this->assertNotNull($active->fresh()->revoked_at);
    }

    public function test_browser_push_preferences_default_disabled_and_require_master_toggle(): void
    {
        $preference = UserNotificationPreference::create(['user_id' => $this->staff->id])->refresh();

        $this->assertFalse($preference->browser_push_enabled);
        $this->assertFalse($preference->critical_browser_push_enabled);
        $this->assertFalse($preference->maintenance_browser_push_enabled);
        $this->assertFalse($preference->wantsCriticalBrowserPush());
        $this->assertFalse($preference->wantsBrowserPushCategory('Work Order'));

        $preference->update(['work_order_browser_push_enabled' => true]);
        $this->assertFalse($preference->fresh()->wantsBrowserPushCategory('Work Order'));

        $preference->update([
            'browser_push_enabled' => true,
            'work_order_browser_push_enabled' => true,
            'critical_browser_push_enabled' => true,
        ]);

        $this->assertTrue($preference->fresh()->wantsBrowserPushCategory('Work Order'));
        $this->assertTrue($preference->fresh()->wantsCriticalBrowserPush());
    }

    public function test_browser_push_preparation_service_reports_readiness_and_manages_subscriptions(): void
    {
        $service = app(BrowserPushPreparationService::class);

        $this->assertFalse($service->userAllowsBrowserPush($this->staff));
        $this->assertFalse($service->userHasActiveSubscription($this->staff));
        $this->assertSame('Browser push disabled', $service->getReadinessForUser($this->staff)['status']);

        $this->staff->notificationPreference()->create([
            'browser_push_enabled' => true,
            'work_order_browser_push_enabled' => true,
        ]);

        $this->staff = $this->staff->fresh();

        $this->assertTrue($service->userAllowsBrowserPush($this->staff));
        $this->assertTrue($service->userAllowsBrowserPush($this->staff, 'Work Order'));
        $this->assertFalse($service->userAllowsBrowserPush($this->staff, 'Warranty'));
        $this->assertSame('No active browser subscriptions', $service->getReadinessForUser($this->staff)['status']);

        $subscription = $service->registerSubscription($this->staff, [
            'endpoint' => 'https://push.example.test/endpoint',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Chrome desktop',
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertSame('https://push.example.test/endpoint', $subscription->endpoint);
        $this->assertTrue($service->userHasActiveSubscription($this->staff));
        $this->assertSame('Ready for future browser push delivery', $service->getReadinessForUser($this->staff)['status']);

        $service->revokeSubscription($subscription);
        $this->assertFalse($service->userHasActiveSubscription($this->staff));
    }

    public function test_browser_push_subscription_endpoints_are_authenticated_and_owner_scoped(): void
    {
        $this->postJson(route('browser-push.subscriptions.store'), [
            'endpoint' => 'https://push.example.test/guest',
        ])->assertUnauthorized();

        $response = $this->actingAs($this->staff)->postJson(route('browser-push.subscriptions.store'), [
            'endpoint' => 'https://push.example.test/staff',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Staff browser',
        ]);

        $response->assertCreated()->assertJson(['status' => 'registered']);
        $subscription = BrowserPushSubscription::firstOrFail();
        $this->assertSame($this->staff->id, $subscription->user_id);

        $other = BrowserPushSubscription::create([
            'user_id' => $this->administrator->id,
            'endpoint' => 'https://push.example.test/admin',
        ]);

        $this->actingAs($this->staff)
            ->deleteJson(route('browser-push.subscriptions.destroy', $other))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->deleteJson(route('browser-push.subscriptions.destroy', $subscription))
            ->assertOk()
            ->assertJson(['status' => 'revoked']);

        $this->assertTrue($subscription->fresh()->isRevoked());
    }

    public function test_notification_preferences_page_displays_and_updates_browser_push_fields(): void
    {
        $this->actingAs($this->staff)
            ->get('/admin/notification-preferences')
            ->assertOk()
            ->assertSee('Browser Push Notifications')
            ->assertSee('Browser push delivery will be enabled in a future PWA phase')
            ->assertSee('Active subscriptions');

        Livewire::actingAs($this->staff)
            ->test(NotificationPreferences::class)
            ->set('browser_push_enabled', true)
            ->set('critical_browser_push_enabled', true)
            ->set('maintenance_browser_push_enabled', true)
            ->set('work_order_browser_push_enabled', true)
            ->set('ai_recommendation_browser_push_enabled', true)
            ->set('lifecycle_browser_push_enabled', true)
            ->set('warranty_browser_push_enabled', true)
            ->set('evidence_browser_push_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $preference = $this->staff->fresh()->notificationPreference;

        $this->assertTrue($preference->browser_push_enabled);
        $this->assertTrue($preference->critical_browser_push_enabled);
        $this->assertTrue($preference->work_order_browser_push_enabled);
    }

    public function test_browser_push_devices_page_renders_current_user_subscriptions_and_revokes_own_only(): void
    {
        $own = BrowserPushSubscription::create([
            'user_id' => $this->staff->id,
            'endpoint' => 'https://push.example.test/own',
            'device_name' => 'Own browser',
            'user_agent' => 'Test browser',
        ]);
        BrowserPushSubscription::create([
            'user_id' => $this->administrator->id,
            'endpoint' => 'https://push.example.test/other',
            'device_name' => 'Other browser',
        ]);

        $this->actingAs($this->staff)
            ->get('/admin/browser-push-devices')
            ->assertOk()
            ->assertSee('Own browser')
            ->assertDontSee('Other browser');

        Livewire::actingAs($this->staff)
            ->test(BrowserPushDevices::class)
            ->assertSee('Browser push readiness')
            ->assertSee('Own browser')
            ->assertDontSee('Other browser')
            ->call('revoke', $own->id)
            ->assertHasNoErrors();

        $this->assertTrue($own->fresh()->isRevoked());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
