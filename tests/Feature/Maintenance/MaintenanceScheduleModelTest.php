<?php

namespace Tests\Feature\Maintenance;

use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Services\EquipmentQrCodeGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceScheduleModelTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
    }

    public function test_maintenance_schedule_can_be_created_with_required_fields(): void
    {
        $schedule = $this->createSchedule();

        $this->assertDatabaseHas('maintenance_schedules', [
            'id' => $schedule->id,
            'equipment_id' => $this->equipment->id,
            'maintenance_type' => 'Preventive check',
            'maintenance_frequency' => 'Monthly',
            'priority' => 'Normal',
            'status' => 'Upcoming',
        ]);
    }

    public function test_maintenance_schedule_belongs_to_equipment_and_assigned_user(): void
    {
        $assignedUser = User::factory()->create();
        $schedule = $this->createSchedule(['assigned_user_id' => $assignedUser->id]);

        $this->assertTrue($schedule->equipment->is($this->equipment));
        $this->assertTrue($schedule->assignedUser->is($assignedUser));
        $this->assertTrue($this->equipment->maintenanceSchedules->contains($schedule));
        $this->assertTrue($this->equipment->latestMaintenanceSchedule->is($schedule));
    }

    public function test_allowed_statuses_frequencies_and_priorities_are_available(): void
    {
        $this->assertSame([
            'Upcoming',
            'Due soon',
            'Due today',
            'Overdue',
            'In progress',
            'Completed',
            'Rescheduled',
            'Cancelled',
        ], MaintenanceSchedule::STATUSES);

        $this->assertSame([
            'Daily',
            'Weekly',
            'Monthly',
            'Quarterly',
            'Semi-annually',
            'Annually',
            'As needed',
        ], MaintenanceSchedule::FREQUENCIES);

        $this->assertSame(['Low', 'Normal', 'High', 'Critical'], MaintenanceSchedule::PRIORITIES);
    }

    public function test_due_today_overdue_due_soon_completed_cancelled_and_incomplete_scopes_work(): void
    {
        $dueToday = $this->createSchedule(['maintenance_type' => 'Due today', 'scheduled_date' => today()]);
        $overdue = $this->createSchedule(['maintenance_type' => 'Overdue', 'scheduled_date' => today()->subDay()]);
        $dueSoon = $this->createSchedule(['maintenance_type' => 'Due soon', 'scheduled_date' => today()->addDays(3)]);
        $completed = $this->createSchedule(['maintenance_type' => 'Completed', 'status' => 'Completed', 'scheduled_date' => today()->subDay()]);
        $cancelled = $this->createSchedule(['maintenance_type' => 'Cancelled', 'status' => 'Cancelled', 'scheduled_date' => today()->addDay()]);

        $this->assertTrue(MaintenanceSchedule::dueToday()->pluck('id')->contains($dueToday->id));
        $this->assertTrue(MaintenanceSchedule::overdue()->pluck('id')->contains($overdue->id));
        $this->assertTrue(MaintenanceSchedule::dueSoon()->pluck('id')->contains($dueSoon->id));
        $this->assertTrue(MaintenanceSchedule::completed()->pluck('id')->contains($completed->id));
        $this->assertTrue(MaintenanceSchedule::cancelled()->pluck('id')->contains($cancelled->id));

        $incomplete = MaintenanceSchedule::incomplete()->pluck('id');
        $this->assertTrue($incomplete->contains($dueToday->id));
        $this->assertFalse($incomplete->contains($completed->id));
        $this->assertFalse($incomplete->contains($cancelled->id));
    }

    public function test_due_maintenance_schedules_relationship_returns_due_items(): void
    {
        $due = $this->createSchedule(['scheduled_date' => today()->addDays(2)]);
        $later = $this->createSchedule(['maintenance_type' => 'Later', 'scheduled_date' => today()->addDays(20)]);

        $ids = $this->equipment->dueMaintenanceSchedules()->pluck('id');

        $this->assertTrue($ids->contains($due->id));
        $this->assertFalse($ids->contains($later->id));
    }

    public function test_calculate_next_scheduled_date_supports_allowed_frequencies(): void
    {
        $baseDate = '2026-01-15';

        $this->assertSame('2026-01-16', $this->createSchedule(['maintenance_frequency' => 'Daily', 'scheduled_date' => $baseDate])->calculateNextScheduledDate()->toDateString());
        $this->assertSame('2026-01-22', $this->createSchedule(['maintenance_frequency' => 'Weekly', 'scheduled_date' => $baseDate])->calculateNextScheduledDate()->toDateString());
        $this->assertSame('2026-02-15', $this->createSchedule(['maintenance_frequency' => 'Monthly', 'scheduled_date' => $baseDate])->calculateNextScheduledDate()->toDateString());
        $this->assertSame('2026-04-15', $this->createSchedule(['maintenance_frequency' => 'Quarterly', 'scheduled_date' => $baseDate])->calculateNextScheduledDate()->toDateString());
        $this->assertSame('2026-07-15', $this->createSchedule(['maintenance_frequency' => 'Semi-annually', 'scheduled_date' => $baseDate])->calculateNextScheduledDate()->toDateString());
        $this->assertSame('2027-01-15', $this->createSchedule(['maintenance_frequency' => 'Annually', 'scheduled_date' => $baseDate])->calculateNextScheduledDate()->toDateString());
        $this->assertNull($this->createSchedule(['maintenance_frequency' => 'As needed', 'scheduled_date' => $baseDate])->calculateNextScheduledDate());
    }

    public function test_completing_schedule_updates_status_completion_fields_and_equipment_dates(): void
    {
        $user = User::factory()->create();
        $schedule = $this->createSchedule([
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => '2026-01-15',
        ]);

        $completed = $schedule->complete($user, 'Completed normally.');

        $this->assertTrue($completed->isCompleted());
        $this->assertNotNull($completed->completed_at);
        $this->assertSame($user->id, $completed->completed_by);
        $this->assertSame('Completed normally.', $completed->completion_remarks);
        $this->assertSame(now()->toDateString(), $this->equipment->fresh()->last_maintenance_date->toDateString());
        $this->assertSame('2026-02-15', $this->equipment->fresh()->next_maintenance_date->toDateString());
    }

    public function test_rescheduling_stores_old_date_updates_new_date_and_status(): void
    {
        $schedule = $this->createSchedule(['scheduled_date' => '2026-01-15']);

        $rescheduled = $schedule->reschedule('2026-02-20', 'Moved due to weather.');

        $this->assertSame('2026-01-15', $rescheduled->rescheduled_from->toDateString());
        $this->assertSame('2026-02-20', $rescheduled->scheduled_date->toDateString());
        $this->assertSame('Rescheduled', $rescheduled->status);
        $this->assertSame('Moved due to weather.', $rescheduled->remarks);
    }

    public function test_cancelling_requires_reason_and_sets_cancellation_fields(): void
    {
        $user = User::factory()->create();
        $schedule = $this->createSchedule();

        try {
            $schedule->cancel('');
            $this->fail('Cancellation reason should be required.');
        } catch (InvalidArgumentException) {
            $this->assertSame('Upcoming', $schedule->fresh()->status);
        }

        $cancelled = $schedule->fresh()->cancel('Duplicate schedule.', $user);

        $this->assertTrue($cancelled->isCancelled());
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame($user->id, $cancelled->cancelled_by);
        $this->assertSame('Duplicate schedule.', $cancelled->cancellation_reason);
    }

    public function test_regression_existing_equipment_qr_generation_and_archive_still_work(): void
    {
        $equipment = $this->createEquipment(['equipment_code' => 'EQ-5A-REGRESSION']);
        $administrator = $this->userWithRole('Administrator');

        app(EquipmentQrCodeGenerator::class)->generate($equipment);
        Storage::disk('public')->assertExists($equipment->fresh()->qr_code_path);

        $this->actingAs($administrator);

        Livewire::test(ListEquipment::class)
            ->callTableAction('archive', $equipment)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($equipment->fresh()->is_archived);
    }

    private function createSchedule(array $overrides = []): MaintenanceSchedule
    {
        return MaintenanceSchedule::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'maintenance_type' => 'Preventive check',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => today()->addDay(),
            'priority' => 'Normal',
            'status' => 'Upcoming',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-TEST-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
