<?php

namespace Tests\Feature\Maintenance;

use App\Filament\Resources\MaintenanceSchedules\Pages\CreateMaintenanceSchedule;
use App\Filament\Resources\MaintenanceSchedules\Pages\ListMaintenanceSchedules;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceScheduleResourceTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private MaintenanceSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->schedule = $this->createSchedule();
    }

    public function test_administrator_can_access_schedule_pages(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        $this->get('/admin/maintenance-schedules')->assertSuccessful();
        $this->get('/admin/maintenance-schedules/create')->assertSuccessful();
        $this->get("/admin/maintenance-schedules/{$this->schedule->id}")->assertSuccessful();
        $this->get("/admin/maintenance-schedules/{$this->schedule->id}/edit")->assertSuccessful();
    }

    public function test_staff_and_technician_can_view_but_cannot_create_or_edit_schedules(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get('/admin/maintenance-schedules')->assertSuccessful();
            $this->get("/admin/maintenance-schedules/{$this->schedule->id}")->assertSuccessful();
            $this->get('/admin/maintenance-schedules/create')->assertForbidden();
            $this->get("/admin/maintenance-schedules/{$this->schedule->id}/edit")->assertForbidden();
        }
    }

    public function test_unauthorized_user_cannot_access_schedule_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/maintenance-schedules')->assertForbidden();
    }

    public function test_schedule_table_searches_relationship_fields_safely(): void
    {
        $assignedUser = $this->userWithRole('Technician');
        $assignedUser->update(['name' => 'Schedule Search Technician']);
        $this->equipment->update(['equipment_name' => 'Schedule Search Analyzer']);
        $this->schedule->update(['assigned_user_id' => $assignedUser->id]);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceSchedules::class)
            ->assertCanSeeTableRecords([$this->schedule])
            ->searchTable('Schedule Search Analyzer')
            ->assertCanSeeTableRecords([$this->schedule])
            ->searchTable('Schedule Search Technician')
            ->assertCanSeeTableRecords([$this->schedule])
            ->searchTable('no matching schedule relationship term')
            ->assertCanNotSeeTableRecords([$this->schedule]);
    }

    public function test_schedule_resource_does_not_use_unsafe_relationship_search_arrays(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/MaintenanceSchedules/MaintenanceScheduleResource.php'));

        $this->assertStringNotContainsString("searchable(['equipment.", $resource);
        $this->assertStringNotContainsString("searchable(['assignedUser.", $resource);
    }

    public function test_administrator_can_create_a_schedule(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(CreateMaintenanceSchedule::class)
            ->fillForm($this->validScheduleData([
                'maintenance_type' => 'Created from form',
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('maintenance_schedules', [
            'equipment_id' => $this->equipment->id,
            'maintenance_type' => 'Created from form',
            'maintenance_frequency' => 'Monthly',
            'priority' => 'Normal',
            'status' => 'Upcoming',
        ]);
    }

    public function test_required_schedule_form_fields_are_validated(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(CreateMaintenanceSchedule::class)
            ->fillForm($this->validScheduleData([
                'equipment_id' => null,
                'maintenance_type' => null,
                'maintenance_frequency' => null,
                'scheduled_date' => null,
                'priority' => null,
                'status' => null,
            ]))
            ->call('create')
            ->assertHasFormErrors([
                'equipment_id' => 'required',
                'maintenance_type' => 'required',
                'maintenance_frequency' => 'required',
                'scheduled_date' => 'required',
                'priority' => 'required',
                'status' => 'required',
            ]);
    }

    public function test_administrator_can_complete_schedule_and_generate_next_recurring_schedule(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceSchedules::class)
            ->callTableAction('complete', $this->schedule, data: [
                'completion_remarks' => 'Completed from table action.',
            ])
            ->assertHasNoTableActionErrors();

        $this->schedule->refresh();

        $this->assertSame('Completed', $this->schedule->status);
        $this->assertNotNull($this->schedule->completed_at);
        $this->assertSame($administrator->id, $this->schedule->completed_by);
        $expectedNextDate = $this->schedule->scheduled_date->copy()->addMonth()->toDateString();

        $this->assertSame(now()->toDateString(), $this->equipment->fresh()->last_maintenance_date->toDateString());
        $this->assertSame($expectedNextDate, $this->equipment->fresh()->next_maintenance_date->toDateString());

        $this->assertDatabaseHas('maintenance_schedules', [
            'generated_from_schedule_id' => $this->schedule->id,
            'equipment_id' => $this->schedule->equipment_id,
            'maintenance_type' => $this->schedule->maintenance_type,
            'maintenance_frequency' => $this->schedule->maintenance_frequency,
            'scheduled_date' => $expectedNextDate,
            'assigned_user_id' => $this->schedule->assigned_user_id,
            'priority' => $this->schedule->priority,
            'status' => 'Upcoming',
        ]);
    }

    public function test_completion_does_not_create_next_schedule_for_as_needed_frequency(): void
    {
        $schedule = $this->createSchedule([
            'maintenance_type' => 'As needed check',
            'maintenance_frequency' => 'As needed',
        ]);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceSchedules::class)
            ->callTableAction('complete', $schedule, data: [
                'completion_remarks' => 'Done.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('maintenance_schedules', [
            'generated_from_schedule_id' => $schedule->id,
        ]);
    }

    public function test_completion_does_not_create_duplicate_next_schedules(): void
    {
        $this->schedule->complete($this->userWithRole('Administrator'), 'Done once.');

        $this->assertSame(1, MaintenanceSchedule::where('generated_from_schedule_id', $this->schedule->id)->count());

        $this->expectException(\InvalidArgumentException::class);
        $this->schedule->fresh()->complete($this->userWithRole('Administrator'), 'Done twice.');
    }

    public function test_completed_and_cancelled_schedules_cannot_be_completed_from_table(): void
    {
        $completed = $this->createSchedule(['maintenance_type' => 'Completed item', 'status' => 'Completed', 'completed_at' => now()]);
        $cancelled = $this->createSchedule(['maintenance_type' => 'Cancelled item', 'status' => 'Cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'No longer needed.']);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceSchedules::class)
            ->assertTableActionHidden('complete', $completed)
            ->assertTableActionHidden('complete', $cancelled);
    }

    public function test_administrator_can_reschedule_schedule(): void
    {
        $oldDate = $this->schedule->scheduled_date->toDateString();
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceSchedules::class)
            ->callTableAction('reschedule', $this->schedule, data: [
                'scheduled_date' => today()->addDays(10)->toDateString(),
                'remarks' => 'Moved from action.',
            ])
            ->assertHasNoTableActionErrors();

        $this->schedule->refresh();

        $this->assertSame($oldDate, $this->schedule->rescheduled_from->toDateString());
        $this->assertSame(today()->addDays(10)->toDateString(), $this->schedule->scheduled_date->toDateString());
        $this->assertSame('Rescheduled', $this->schedule->status);
        $this->assertSame('Moved from action.', $this->schedule->remarks);
    }

    public function test_completed_and_cancelled_schedules_cannot_be_rescheduled(): void
    {
        $completed = $this->createSchedule(['maintenance_type' => 'Completed item', 'status' => 'Completed', 'completed_at' => now()]);
        $cancelled = $this->createSchedule(['maintenance_type' => 'Cancelled item', 'status' => 'Cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'No longer needed.']);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceSchedules::class)
            ->assertTableActionHidden('reschedule', $completed)
            ->assertTableActionHidden('reschedule', $cancelled);
    }

    public function test_administrator_can_cancel_schedule(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceSchedules::class)
            ->callTableAction('cancel', $this->schedule, data: [
                'cancellation_reason' => 'Duplicate schedule.',
            ])
            ->assertHasNoTableActionErrors();

        $this->schedule->refresh();

        $this->assertSame('Cancelled', $this->schedule->status);
        $this->assertNotNull($this->schedule->cancelled_at);
        $this->assertSame($administrator->id, $this->schedule->cancelled_by);
        $this->assertSame('Duplicate schedule.', $this->schedule->cancellation_reason);
    }

    public function test_cancellation_requires_reason(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceSchedules::class)
            ->callTableAction('cancel', $this->schedule, data: [
                'cancellation_reason' => null,
            ])
            ->assertHasTableActionErrors(['cancellation_reason' => 'required']);
    }

    public function test_completed_and_cancelled_schedules_cannot_be_cancelled(): void
    {
        $completed = $this->createSchedule(['maintenance_type' => 'Completed item', 'status' => 'Completed', 'completed_at' => now()]);
        $cancelled = $this->createSchedule(['maintenance_type' => 'Cancelled item', 'status' => 'Cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'No longer needed.']);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceSchedules::class)
            ->assertTableActionHidden('cancel', $completed)
            ->assertTableActionHidden('cancel', $cancelled);
    }

    public function test_staff_and_technician_cannot_manage_schedule_actions(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            Livewire::test(ListMaintenanceSchedules::class)
                ->assertTableActionHidden('complete', $this->schedule)
                ->assertTableActionHidden('reschedule', $this->schedule)
                ->assertTableActionHidden('cancel', $this->schedule);
        }
    }

    public function test_dynamic_due_status_display_works(): void
    {
        $overdue = $this->createSchedule(['maintenance_type' => 'Overdue item', 'scheduled_date' => today()->subDay()]);
        $dueToday = $this->createSchedule(['maintenance_type' => 'Due today item', 'scheduled_date' => today()]);
        $dueSoon = $this->createSchedule(['maintenance_type' => 'Due soon item', 'scheduled_date' => today()->addDays(3)]);

        $this->assertSame('Overdue', $overdue->displayStatus());
        $this->assertSame('Due today', $dueToday->displayStatus());
        $this->assertSame('Due soon', $dueSoon->displayStatus());
    }

    private function validScheduleData(array $overrides = []): array
    {
        return array_merge([
            'equipment_id' => $this->equipment->id,
            'maintenance_type' => 'Preventive inspection',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => today()->addDay()->toDateString(),
            'assigned_user_id' => null,
            'priority' => 'Normal',
            'checklist_instructions' => 'Inspect and clean equipment.',
            'status' => 'Upcoming',
            'remarks' => 'Created in test.',
        ], $overrides);
    }

    private function createSchedule(array $overrides = []): MaintenanceSchedule
    {
        return MaintenanceSchedule::create(array_merge($this->validScheduleData(), $overrides));
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
