<?php

namespace Tests\Feature\Notifications;

use App\Filament\Pages\NotificationPreferences;
use App\Filament\Resources\SystemNotifications\Pages\ListSystemNotifications;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\WorkOrder;
use App\Services\SystemNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $staff;

    private EquipmentCategory $category;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->staff = $this->userWithRole('Staff');
        $this->category = EquipmentCategory::create(['name' => 'Notification Equipment']);
        $this->location = Location::create(['name' => 'Notification Lab', 'type' => 'Room']);
    }

    public function test_notification_model_preferences_and_scopes_work(): void
    {
        $own = SystemNotification::create([
            'user_id' => $this->staff->id,
            'title' => 'Unread own alert',
            'message' => 'Own alert message',
            'notification_type' => 'Warning',
            'priority' => 'High',
            'category' => 'Work Order',
            'generated_at' => now(),
        ]);
        $global = SystemNotification::create([
            'title' => 'Global alert',
            'message' => 'Global alert message',
            'notification_type' => 'Information',
            'priority' => 'Normal',
            'category' => 'System',
            'generated_at' => now(),
        ]);
        $expired = SystemNotification::create([
            'user_id' => $this->staff->id,
            'title' => 'Expired alert',
            'message' => 'Expired alert message',
            'notification_type' => 'Critical',
            'priority' => 'Critical',
            'category' => 'Evidence',
            'generated_at' => now(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($own->user->is($this->staff));
        $this->assertNull($global->user);
        $this->assertTrue($own->isUnread());
        $this->assertTrue($own->markAsRead()->isRead());
        $this->assertTrue($own->markAsUnread()->isUnread());
        $this->assertTrue($expired->isExpired());
        $this->assertSame(3, SystemNotification::unread()->count());
        $this->assertSame(0, SystemNotification::read()->count());
        $this->assertSame(2, SystemNotification::active()->count());
        $this->assertSame(1, SystemNotification::expired()->count());
        $this->assertSame(1, SystemNotification::critical()->count());
        $this->assertSame(2, SystemNotification::highPriority()->count());
        $this->assertSame(1, SystemNotification::category('Work Order')->count());
        $this->assertSame(2, SystemNotification::forUser($this->staff)->count());
        $this->assertSame(1, SystemNotification::global()->count());
        $this->assertSame('High', $own->getPriorityLabel());
        $this->assertSame('Warning', $own->getTypeLabel());
        $this->assertSame('Work Order', $own->getCategoryLabel());

        $preference = UserNotificationPreference::create(['user_id' => $this->staff->id]);

        $this->assertTrue($this->staff->fresh()->notificationPreference->is($preference));
        $this->assertTrue($this->staff->fresh()->wantsNotificationCategory('Warranty'));
        $this->assertTrue(User::factory()->create()->wantsNotificationCategory('Evidence'));
    }

    public function test_service_creates_notifications_prevents_duplicates_respects_preferences_and_marks_all_read(): void
    {
        $service = app(SystemNotificationService::class);
        $equipment = $this->createEquipment();

        $created = $service->createForUser(
            $this->staff,
            'Assigned work order alert',
            'A work order was assigned.',
            'Information',
            'Normal',
            'Work Order',
            $equipment
        );
        $global = $service->createGlobal('System notice', 'A global notice was created.');
        $duplicate = $service->createForUser(
            $this->staff,
            'Assigned work order alert',
            'A work order was assigned.',
            'Information',
            'Normal',
            'Work Order',
            $equipment
        );

        UserNotificationPreference::create([
            'user_id' => $this->staff->id,
            'work_order_alerts' => false,
        ]);

        $disabled = $service->createForUser($this->staff->fresh(), 'Suppressed work order alert', 'Suppressed.', category: 'Work Order');

        $this->assertNotNull($created);
        $this->assertNotNull($global);
        $this->assertNull($duplicate);
        $this->assertNull($disabled);
        $this->assertSame(2, SystemNotification::count());
        $this->assertSame(2, $service->markAllAsRead($this->staff));
        $this->assertSame(0, SystemNotification::visibleTo($this->staff)->unread()->count());
    }

    public function test_notification_generation_rules_and_command_create_expected_notifications(): void
    {
        $equipment = $this->createEquipment(['warranty_expiration_date' => now()->addDays(5)->toDateString()]);
        $overdueEquipment = $this->createEquipment(['equipment_code' => 'EQ-NOT-OVERDUE']);

        MaintenanceSchedule::create([
            'equipment_id' => $equipment->id,
            'maintenance_type' => 'Due today calibration',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => today(),
            'assigned_user_id' => $this->staff->id,
            'priority' => 'High',
            'status' => 'Upcoming',
        ]);
        MaintenanceSchedule::create([
            'equipment_id' => $overdueEquipment->id,
            'maintenance_type' => 'Overdue calibration',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => today()->subDay(),
            'assigned_user_id' => $this->staff->id,
            'priority' => 'Critical',
            'status' => 'Upcoming',
        ]);
        WorkOrder::create([
            'equipment_id' => $equipment->id,
            'created_by' => $this->administrator->id,
            'assigned_to' => $this->staff->id,
            'title' => 'Assigned work order',
            'problem_description' => 'Assigned work order',
            'priority' => 'Normal',
            'status' => 'Assigned',
        ]);
        WorkOrder::create([
            'equipment_id' => $equipment->id,
            'created_by' => $this->administrator->id,
            'assigned_to' => $this->staff->id,
            'title' => 'Verification work order',
            'problem_description' => 'Verification work order',
            'priority' => 'High',
            'status' => 'For verification',
            'action_performed' => 'Checked equipment',
            'final_equipment_condition' => 'Good',
            'final_operational_status' => 'Available',
        ]);
        MaintenanceRequest::create([
            'equipment_id' => $equipment->id,
            'submitted_by' => $this->staff->id,
            'problem_description' => 'Submitted request',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ]);
        MaintenanceRecommendation::create([
            'equipment_id' => $equipment->id,
            'rule_key' => 'defective_without_work_order',
            'title' => 'Critical recommendation alert',
            'explanation' => 'Critical recommendation.',
            'risk_level' => 'Critical',
            'recommended_action' => 'Create work order',
            'generated_at' => now(),
            'status' => 'Open',
        ]);
        EquipmentLifecycleProfile::create([
            'equipment_id' => $equipment->id,
            'health_score' => 25,
            'health_grade' => 'Critical',
            'lifecycle_status' => 'Replacement Candidate',
            'replacement_recommendation' => 'Replace Equipment',
        ]);

        $this->artisan('notifications:generate')
            ->assertSuccessful()
            ->expectsOutputToContain('Notifications created:')
            ->expectsOutputToContain('Categories checked:');

        $this->assertDatabaseHas('system_notifications', ['category' => 'Preventive Maintenance', 'priority' => 'High']);
        $this->assertDatabaseHas('system_notifications', ['category' => 'Preventive Maintenance', 'priority' => 'Critical']);
        $this->assertDatabaseHas('system_notifications', ['category' => 'Work Order', 'title' => 'Work order assigned: '.$this->firstWorkOrderNumber('Assigned work order')]);
        $this->assertDatabaseHas('system_notifications', ['category' => 'Work Order', 'priority' => 'High']);
        $this->assertDatabaseHas('system_notifications', ['category' => 'Maintenance Request']);
        $this->assertDatabaseHas('system_notifications', ['category' => 'AI Recommendation', 'priority' => 'Critical']);
        $this->assertDatabaseHas('system_notifications', ['category' => 'Equipment Lifecycle', 'priority' => 'Critical']);
        $this->assertDatabaseHas('system_notifications', ['category' => 'Warranty', 'priority' => 'High']);
        $this->assertDatabaseHas('system_notifications', ['category' => 'Evidence', 'priority' => 'High']);
    }

    public function test_notification_center_and_preferences_ui_authorization_and_actions(): void
    {
        $own = SystemNotification::create([
            'user_id' => $this->staff->id,
            'title' => 'Own private notification',
            'message' => 'Visible to owner',
            'notification_type' => 'Information',
            'priority' => 'Normal',
            'category' => 'System',
            'generated_at' => now(),
        ]);
        SystemNotification::create([
            'title' => 'Global notification',
            'message' => 'Visible to authenticated users',
            'notification_type' => 'Information',
            'priority' => 'Normal',
            'category' => 'System',
            'generated_at' => now(),
        ]);
        $other = SystemNotification::create([
            'user_id' => $this->administrator->id,
            'title' => 'Other private notification',
            'message' => 'Hidden from staff',
            'notification_type' => 'Information',
            'priority' => 'Normal',
            'category' => 'System',
            'generated_at' => now(),
        ]);

        $this->get('/admin/system-notifications')->assertRedirect('/admin/login');

        $this->actingAs($this->staff)
            ->get('/admin/system-notifications')
            ->assertOk()
            ->assertSee('Own private notification')
            ->assertSee('Global notification')
            ->assertDontSee('Other private notification');

        Livewire::actingAs($this->staff)
            ->test(ListSystemNotifications::class)
            ->callTableAction('markAsRead', $own)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($own->fresh()->isRead());

        Livewire::actingAs($this->staff)
            ->test(ListSystemNotifications::class)
            ->callTableAction('markAsUnread', $own->fresh())
            ->assertHasNoTableActionErrors()
            ->callAction('markAllAsRead')
            ->assertHasNoActionErrors();

        $this->assertTrue($own->fresh()->isRead());

        $this->assertFalse($this->staff->can('markRead', $other));

        $this->actingAs($this->staff)
            ->get('/admin/notification-preferences')
            ->assertOk()
            ->assertSee('Notification Preferences');

        Livewire::actingAs($this->staff)
            ->test(NotificationPreferences::class)
            ->set('warranty_alerts', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($this->staff->fresh()->notificationPreference->warranty_alerts);
        $this->assertSame(1, UserNotificationPreference::where('user_id', $this->staff->id)->count());
    }

    private function firstWorkOrderNumber(string $title): string
    {
        return WorkOrder::where('title', $title)->value('work_order_number');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-NOT-'.uniqid(),
            'equipment_name' => 'Notification Equipment',
            'equipment_category_id' => $this->category->id,
            'current_location_id' => $this->location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
            'acquisition_date' => now()->subYear()->toDateString(),
        ], $overrides));
    }
}
