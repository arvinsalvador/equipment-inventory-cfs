<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Filament\Resources\MaintenanceRecommendations\Pages\ListMaintenanceRecommendations;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRecommendationResourceTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private MaintenanceRecommendation $recommendation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->recommendation = $this->createRecommendation();
    }

    public function test_authorized_users_can_access_recommendation_list(): void
    {
        foreach (['Administrator', 'Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get('/admin/maintenance-recommendations')->assertSuccessful();
        }
    }

    public function test_unauthorized_user_cannot_access_recommendation_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/maintenance-recommendations')->assertForbidden();
    }

    public function test_administrator_can_mark_recommendation_as_reviewed(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('markReviewed', $this->recommendation)
            ->assertHasNoTableActionErrors();

        $this->assertSame('Reviewed', $this->recommendation->fresh()->status);
        $this->assertSame($administrator->id, $this->recommendation->fresh()->reviewed_by);
    }

    public function test_administrator_can_mark_recommendation_as_resolved(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('markResolved', $this->recommendation)
            ->assertHasNoTableActionErrors();

        $this->assertSame('Resolved', $this->recommendation->fresh()->status);
        $this->assertSame($administrator->id, $this->recommendation->fresh()->resolved_by);
    }

    public function test_administrator_can_dismiss_recommendation(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('dismiss', $this->recommendation)
            ->assertHasNoTableActionErrors();

        $this->assertSame('Dismissed', $this->recommendation->fresh()->status);
        $this->assertSame($administrator->id, $this->recommendation->fresh()->resolved_by);
    }

    public function test_recommendation_list_shows_suggested_action_and_action_status(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceRecommendations::class)
            ->assertSee('Suggested action')
            ->assertSee('Action status')
            ->assertSee('Create Preventive Maintenance Schedule')
            ->assertSee('Pending');
    }

    public function test_administrator_can_approve_and_execute_approved_action(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('approveAction', $this->recommendation, data: [
                'action_notes' => 'Approved by admin.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Approved', $this->recommendation->fresh()->action_status);
        $this->assertSame('Approved', $this->recommendation->fresh()->status);
        $this->assertNotNull($this->recommendation->fresh()->linked_maintenance_request_id);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('executeAction', $this->recommendation->fresh(), data: [
                'action_notes' => 'Executed by admin.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Executed', $this->recommendation->fresh()->action_status);
        $this->assertNotNull($this->recommendation->fresh()->linked_maintenance_schedule_id);
    }

    public function test_approve_action_creates_and_links_maintenance_request_for_actionable_recommendation(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $recommendation = $this->createRecommendation([
            'rule_key' => 'overdue_maintenance',
            'risk_level' => 'Critical',
        ]);

        $this->actingAs($administrator);

        $beforeCount = MaintenanceRequest::count();

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('approveAction', $recommendation, data: [
                'action_notes' => 'Approve and create request.',
            ])
            ->assertHasNoTableActionErrors();

        $recommendation->refresh();
        $request = $recommendation->linkedMaintenanceRequest;

        $this->assertSame($beforeCount + 1, MaintenanceRequest::count());
        $this->assertNotNull($request);
        $this->assertSame('Approved', $recommendation->status);
        $this->assertSame('Approved', $recommendation->action_status);
        $this->assertSame($recommendation->equipment_id, $request->equipment_id);
        $this->assertSame('Critical', $request->severity);
        $this->assertSame('Submitted', $request->status);

        $this->get('/admin/maintenance-requests')
            ->assertSuccessful()
            ->assertSee($request->request_number);
    }

    public function test_duplicate_approve_action_does_not_create_duplicate_maintenance_request(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $recommendation = $this->createRecommendation(['rule_key' => 'due_soon']);

        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('approveAction', $recommendation, data: [
                'action_notes' => 'First approval.',
            ])
            ->assertHasNoTableActionErrors();

        $firstRequestId = $recommendation->fresh()->linked_maintenance_request_id;

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('approveAction', $recommendation->fresh(), data: [
                'action_notes' => 'Duplicate approval.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($firstRequestId, $recommendation->fresh()->linked_maintenance_request_id);
        $this->assertSame(1, MaintenanceRequest::where('equipment_id', $recommendation->equipment_id)->count());
    }

    public function test_approve_action_approves_non_actionable_recommendation_without_request(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $recommendation = $this->createRecommendation([
            'rule_key' => 'unknown_rule',
            'suggested_action_type' => 'monitor_only',
        ]);

        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('approveAction', $recommendation, data: [
                'action_notes' => 'Approve monitor only.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Approved', $recommendation->fresh()->status);
        $this->assertSame('Approved', $recommendation->fresh()->action_status);
        $this->assertNull($recommendation->fresh()->linked_maintenance_request_id);
        $this->assertSame(0, MaintenanceRequest::count());
    }

    public function test_staff_and_technician_cannot_mark_recommendation_reviewed_by_default(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            Livewire::test(ListMaintenanceRecommendations::class)
                ->assertTableActionHidden('markReviewed', $this->recommendation)
                ->assertTableActionHidden('markResolved', $this->recommendation)
                ->assertTableActionHidden('dismiss', $this->recommendation)
                ->assertTableActionHidden('approveAction', $this->recommendation)
                ->assertTableActionHidden('rejectAction', $this->recommendation)
                ->assertTableActionHidden('executeAction', $this->recommendation)
                ->assertTableActionHidden('cancelAction', $this->recommendation);
        }
    }

    private function createRecommendation(array $overrides = []): MaintenanceRecommendation
    {
        return MaintenanceRecommendation::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Preventive maintenance is overdue',
            'explanation' => 'The equipment maintenance date has already passed.',
            'risk_level' => 'High',
            'recommended_action' => 'Schedule maintenance immediately.',
            'generated_at' => now(),
            'status' => 'Open',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-FIL-'.uniqid(),
            'equipment_name' => 'Filament Equipment',
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
