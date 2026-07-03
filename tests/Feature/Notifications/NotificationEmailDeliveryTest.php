<?php

namespace Tests\Feature\Notifications;

use App\Filament\Pages\NotificationPreferences;
use App\Mail\CriticalNotificationMail;
use App\Mail\NotificationDigestMail;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\NotificationEmailService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->staff = $this->userWithRole('Staff');
    }

    public function test_email_preferences_default_disabled_and_can_be_enabled_with_validation(): void
    {
        $preference = UserNotificationPreference::create(['user_id' => $this->staff->id])->refresh();

        $this->assertFalse($preference->email_notifications_enabled);
        $this->assertFalse($preference->wantsImmediateCriticalEmail());
        $this->assertFalse($preference->wantsDailyDigestEmail());
        $this->assertFalse($preference->wantsWeeklyDigestEmail());

        Livewire::actingAs($this->staff)
            ->test(NotificationPreferences::class)
            ->set('email_notifications_enabled', true)
            ->set('immediate_critical_email_enabled', true)
            ->set('daily_digest_email_enabled', true)
            ->set('weekly_digest_email_enabled', true)
            ->set('digest_time', '08:30')
            ->set('digest_day_of_week', 'Friday')
            ->call('save')
            ->assertHasNoErrors();

        $preference = $this->staff->fresh()->notificationPreference;

        $this->assertTrue($preference->wantsImmediateCriticalEmail());
        $this->assertTrue($preference->wantsDailyDigestEmail());
        $this->assertTrue($preference->wantsWeeklyDigestEmail());
        $this->assertSame('08:30', $preference->digest_time);
        $this->assertSame('Friday', $preference->digest_day_of_week);

        Livewire::actingAs($this->staff)
            ->test(NotificationPreferences::class)
            ->set('digest_time', '25:00')
            ->set('digest_day_of_week', 'Funday')
            ->call('save')
            ->assertHasErrors(['digest_time', 'digest_day_of_week']);
    }

    public function test_mailables_render_notification_content_and_action_links(): void
    {
        $notification = $this->createNotification([
            'title' => 'Critical pump alert',
            'message' => 'Pump requires immediate attention.',
            'priority' => 'Critical',
            'notification_type' => 'Critical',
            'category' => 'Equipment Lifecycle',
            'action_url' => 'https://example.test/admin/equipment/1',
        ]);

        $criticalHtml = (new CriticalNotificationMail($notification, $this->staff))->render();
        $digestHtml = (new NotificationDigestMail($this->staff, 'daily', SystemNotification::query()->get()))->render();

        $this->assertStringContainsString('Critical pump alert', $criticalHtml);
        $this->assertStringContainsString('Pump requires immediate attention.', $criticalHtml);
        $this->assertStringContainsString('https://example.test/admin/equipment/1', $criticalHtml);
        $this->assertStringContainsString('daily notification digest', strtolower($digestHtml));
        $this->assertStringContainsString('Critical pump alert', $digestHtml);
        $this->assertStringContainsString('https://example.test/admin/equipment/1', $digestHtml);
    }

    public function test_immediate_critical_email_sends_only_when_enabled_and_records_status(): void
    {
        Mail::fake();

        $service = app(NotificationEmailService::class);
        $critical = $this->createNotification([
            'user_id' => $this->staff->id,
            'priority' => 'Critical',
            'notification_type' => 'Critical',
        ]);
        $nonCritical = $this->createNotification([
            'user_id' => $this->staff->id,
            'title' => 'Normal notice',
            'priority' => 'Normal',
            'notification_type' => 'Information',
        ]);

        $this->assertFalse($service->sendNotificationEmail($critical));
        Mail::assertNothingSent();

        $this->staff->notificationPreference()->create([
            'email_notifications_enabled' => true,
            'immediate_critical_email_enabled' => true,
        ]);

        $this->assertFalse($service->sendNotificationEmail($nonCritical));
        $this->assertTrue($service->sendNotificationEmail($critical->fresh()));
        $this->assertFalse($service->sendNotificationEmail($critical->fresh()));

        Mail::assertSent(CriticalNotificationMail::class, 1);
        $this->assertNotNull($critical->fresh()->email_sent_at);
        $this->assertSame(1, $critical->fresh()->email_delivery_attempts);
    }

    public function test_email_failure_records_failure_fields(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('Mail transport failed.'));

        $this->staff->notificationPreference()->create([
            'email_notifications_enabled' => true,
            'immediate_critical_email_enabled' => true,
        ]);

        $notification = $this->createNotification([
            'user_id' => $this->staff->id,
            'priority' => 'Critical',
            'notification_type' => 'Critical',
        ]);

        $this->assertFalse(app(NotificationEmailService::class)->sendNotificationEmail($notification));
        $notification = $notification->fresh();

        $this->assertNull($notification->email_sent_at);
        $this->assertNotNull($notification->email_failed_at);
        $this->assertStringContainsString('Mail transport failed', $notification->email_failure_reason);
        $this->assertSame(1, $notification->email_delivery_attempts);
    }

    public function test_daily_and_weekly_digests_send_to_opted_in_users_without_marking_read(): void
    {
        Mail::fake();

        $this->staff->notificationPreference()->create([
            'email_notifications_enabled' => true,
            'daily_digest_email_enabled' => true,
            'weekly_digest_email_enabled' => true,
        ]);

        $notification = $this->createNotification([
            'user_id' => $this->staff->id,
            'title' => 'Unread digest notification',
        ]);

        $service = app(NotificationEmailService::class);
        $daily = $service->sendDailyDigests();
        $weekly = $service->sendWeeklyDigests();

        $this->assertSame(1, $daily['users_checked']);
        $this->assertSame(1, $daily['emails_sent']);
        $this->assertSame(1, $weekly['users_checked']);
        $this->assertSame(1, $weekly['emails_sent']);
        $this->assertTrue($notification->fresh()->isUnread());

        Mail::assertSent(NotificationDigestMail::class, 2);
    }

    public function test_digest_skips_users_without_email_preferences_or_unread_notifications(): void
    {
        Mail::fake();

        $this->staff->notificationPreference()->create([
            'email_notifications_enabled' => true,
            'daily_digest_email_enabled' => true,
        ]);

        $summary = app(NotificationEmailService::class)->sendDigestForUser($this->staff, 'daily');

        $this->assertSame(1, $summary['users_checked']);
        $this->assertSame(0, $summary['emails_sent']);
        $this->assertSame(1, $summary['skipped']);

        Mail::assertNothingSent();
    }

    public function test_notification_commands_and_scheduler_registration(): void
    {
        Mail::fake();

        $this->staff->notificationPreference()->create([
            'email_notifications_enabled' => true,
            'immediate_critical_email_enabled' => true,
            'daily_digest_email_enabled' => true,
            'weekly_digest_email_enabled' => true,
        ]);
        $this->createNotification([
            'user_id' => $this->staff->id,
            'priority' => 'Critical',
            'notification_type' => 'Critical',
        ]);

        $this->artisan('notifications:generate --send-critical-emails')
            ->assertSuccessful()
            ->expectsOutputToContain('Immediate critical emails processed.');

        $this->artisan('notifications:send-daily-digest')
            ->assertSuccessful()
            ->expectsOutputToContain('Daily notification digests processed.');

        $this->artisan('notifications:send-weekly-digest')
            ->assertSuccessful()
            ->expectsOutputToContain('Weekly notification digests processed.');

        $this->artisan('schedule:list')
            ->assertSuccessful()
            ->expectsOutputToContain('notifications:generate')
            ->expectsOutputToContain('notifications:send-daily-digest')
            ->expectsOutputToContain('notifications:send-weekly-digest');
    }

    public function test_preferences_page_displays_email_fields_and_user_cannot_edit_another_users_preferences(): void
    {
        $this->actingAs($this->staff)
            ->get('/admin/notification-preferences')
            ->assertOk()
            ->assertSee('Email delivery')
            ->assertSee('Enable email notifications')
            ->assertSee('Preferred weekly digest day');

        Livewire::actingAs($this->staff)
            ->test(NotificationPreferences::class)
            ->set('email_notifications_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($this->staff->fresh()->notificationPreference->email_notifications_enabled);
        $this->assertNull($this->administrator->fresh()->notificationPreference);
    }

    private function createNotification(array $overrides = []): SystemNotification
    {
        return SystemNotification::create(array_merge([
            'user_id' => null,
            'title' => 'System notification',
            'message' => 'System notification message.',
            'notification_type' => 'Information',
            'priority' => 'Normal',
            'category' => 'System',
            'generated_at' => now(),
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
