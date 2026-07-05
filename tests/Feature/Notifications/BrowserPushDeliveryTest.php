<?php

namespace Tests\Feature\Notifications;

use App\Models\BrowserPushSubscription;
use App\Models\SystemNotification;
use App\Models\User;
use App\Notifications\BrowserPushNotification;
use App\Services\BrowserPushService;
use App\Services\SystemNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrowserPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->technician = User::factory()->create();
        $this->technician->assignRole(Role::findByName('Technician'));

        $this->administrator = User::factory()->create();
        $this->administrator->assignRole(Role::findByName('Administrator'));

        config([
            'webpush.vapid.public_key' => 'test-public-key',
            'webpush.vapid.private_key' => 'test-private-key',
            'webpush.vapid.subject' => 'mailto:admin@seims.site',
        ]);
    }

    public function test_enabled_category_allows_browser_push_for_existing_notification_flow(): void
    {
        Notification::fake();
        $this->preparePushUser($this->technician, ['work_order_browser_push_enabled' => true]);

        app(SystemNotificationService::class)->createForUser(
            $this->technician,
            'Work order assigned',
            'A work order has been assigned.',
            'Information',
            'Normal',
            'Work Order',
            null,
            '/admin/work-orders/1',
        );

        $this->assertSame(1, SystemNotification::count());
        Notification::assertSentTo($this->technician, BrowserPushNotification::class);
    }

    public function test_disabled_category_prevents_browser_push_without_blocking_database_notification(): void
    {
        Notification::fake();
        $this->preparePushUser($this->technician, ['work_order_browser_push_enabled' => false]);

        app(SystemNotificationService::class)->createForUser(
            $this->technician,
            'Work order assigned',
            'A work order has been assigned.',
            'Information',
            'Normal',
            'Work Order',
        );

        $this->assertSame(1, SystemNotification::count());
        Notification::assertNothingSent();
    }

    public function test_global_critical_recommendation_pushes_to_eligible_administrators(): void
    {
        Notification::fake();
        $this->preparePushUser($this->administrator, ['ai_recommendation_browser_push_enabled' => true]);

        app(SystemNotificationService::class)->createGlobal(
            'Critical recommendation',
            'Review the critical recommendation.',
            'Critical',
            'Critical',
            'AI Recommendation',
            null,
            '/admin/maintenance-recommendations/1',
        );

        Notification::assertSentTo($this->administrator, BrowserPushNotification::class);
    }

    public function test_missing_vapid_keys_skip_delivery_gracefully(): void
    {
        Notification::fake();
        config([
            'webpush.vapid.public_key' => null,
            'webpush.vapid.private_key' => null,
        ]);

        $this->preparePushUser($this->technician, ['work_order_browser_push_enabled' => true]);

        $result = app(BrowserPushService::class)->sendToUser(
            $this->technician,
            'Work order assigned',
            'A work order has been assigned.',
            'Work Order',
        );

        $this->assertFalse($result['attempted']);
        $this->assertSame('VAPID keys are not configured', $result['reason']);
        Notification::assertNothingSent();
    }

    /**
     * @param  array<string, bool>  $categoryOverrides
     */
    private function preparePushUser(User $user, array $categoryOverrides): void
    {
        $user->notificationPreference()->create(array_merge([
            'browser_push_enabled' => true,
            'critical_browser_push_enabled' => true,
            'maintenance_browser_push_enabled' => true,
            'work_order_browser_push_enabled' => true,
            'maintenance_request_browser_push_enabled' => true,
            'ai_recommendation_browser_push_enabled' => true,
            'lifecycle_browser_push_enabled' => true,
            'warranty_browser_push_enabled' => true,
            'evidence_browser_push_enabled' => true,
            'budget_browser_push_enabled' => true,
            'asset_action_browser_push_enabled' => true,
            'executive_browser_push_enabled' => true,
        ], $categoryOverrides));

        BrowserPushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/'.$user->id,
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
        ]);
    }
}
