<?php

namespace Tests\Feature\MaintenanceRequests;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $submitter;

    private MaintenanceRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->submitter = $this->userWithRole('Staff');
        $this->request = $this->createRequest(['submitted_by' => $this->submitter->id]);
    }

    public function test_administrator_can_review_requests(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->assertTrue($administrator->can('review', $this->request));
        $this->assertTrue($administrator->can('approve', $this->request));
        $this->assertTrue($administrator->can('reject', $this->request));
        $this->assertTrue($administrator->can('convert', $this->request));
    }

    public function test_staff_can_submit_view_own_and_cancel_open_request_but_not_review(): void
    {
        $this->assertTrue($this->submitter->can('create', MaintenanceRequest::class));
        $this->assertTrue($this->submitter->can('view', $this->request));
        $this->assertTrue($this->submitter->can('cancel', $this->request));
        $this->assertFalse($this->submitter->can('review', $this->request));
        $this->assertFalse($this->submitter->can('approve', $this->request));
    }

    public function test_submitter_cannot_cancel_closed_or_approved_request(): void
    {
        foreach (['Approved', 'Converted', 'Rejected', 'Cancelled'] as $status) {
            $request = $this->createRequest([
                'submitted_by' => $this->submitter->id,
                'status' => $status,
            ]);

            $this->assertFalse($this->submitter->can('cancel', $request));
        }
    }

    public function test_technician_cannot_review_requests_by_default(): void
    {
        $technician = $this->userWithRole('Technician');

        $this->assertFalse($technician->can('review', $this->request));
        $this->assertFalse($technician->can('approve', $this->request));
        $this->assertFalse($technician->can('reject', $this->request));
        $this->assertFalse($technician->can('convert', $this->request));
    }

    public function test_unauthorized_user_cannot_submit_requests(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('create', MaintenanceRequest::class));
    }

    public function test_user_with_equipment_view_can_view_related_request(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('equipment.view');

        $this->assertTrue($user->can('view', $this->request));
    }

    public function test_permanent_delete_is_denied(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->assertFalse($administrator->can('delete', $this->request));
        $this->assertFalse($administrator->can('forceDelete', $this->request));
    }

    private function createRequest(array $overrides = []): MaintenanceRequest
    {
        return MaintenanceRequest::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'submitted_by' => $this->submitter?->id ?? User::factory()->create()->id,
            'problem_description' => 'Policy test request.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ], $overrides));
    }

    private function createEquipment(): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create([
            'equipment_code' => 'EQ-TEST-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
