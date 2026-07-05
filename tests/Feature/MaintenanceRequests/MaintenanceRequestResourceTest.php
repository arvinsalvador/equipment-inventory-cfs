<?php

namespace Tests\Feature\MaintenanceRequests;

use App\Filament\Resources\MaintenanceRequests\Pages\CreateMaintenanceRequest;
use App\Filament\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRequestResourceTest extends TestCase
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

    public function test_administrator_can_access_request_pages(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        $this->get('/admin/maintenance-requests')->assertSuccessful();
        $this->get('/admin/maintenance-requests/create')->assertSuccessful();
        $this->get("/admin/maintenance-requests/{$this->request->id}")->assertSuccessful();
        $this->get("/admin/maintenance-requests/{$this->request->id}/edit")->assertSuccessful();
    }

    public function test_staff_can_create_maintenance_request(): void
    {
        $staff = $this->userWithRole('Staff');
        $this->actingAs($staff);

        Livewire::test(CreateMaintenanceRequest::class)
            ->fillForm([
                'equipment_id' => $this->equipment->id,
                'problem_description' => 'Created from request resource.',
                'severity' => 'Moderate',
                'remarks' => 'Please check this equipment.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('maintenance_requests', [
            'equipment_id' => $this->equipment->id,
            'submitted_by' => $staff->id,
            'problem_description' => 'Created from request resource.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ]);
    }

    public function test_technician_cannot_review_requests_by_default(): void
    {
        $this->actingAs($this->userWithRole('Technician'));

        Livewire::test(ListMaintenanceRequests::class)
            ->assertTableActionHidden('approve', $this->request)
            ->assertTableActionHidden('reject', $this->request)
            ->assertTableActionHidden('convertToWorkOrder', $this->request);
    }

    public function test_administrator_can_approve_request(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRequests::class)
            ->callTableAction('approve', $this->request, data: [
                'review_remarks' => 'Approved from table.',
            ])
            ->assertHasNoTableActionErrors();

        $this->request->refresh();

        $this->assertSame('Approved', $this->request->status);
        $this->assertSame($administrator->id, $this->request->reviewed_by);
        $this->assertNotNull($this->request->reviewed_at);
        $this->assertSame('Approved from table.', $this->request->review_remarks);
    }

    public function test_administrator_can_reject_request(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRequests::class)
            ->callTableAction('reject', $this->request, data: [
                'rejection_reason' => 'Insufficient details.',
            ])
            ->assertHasNoTableActionErrors();

        $this->request->refresh();

        $this->assertSame('Rejected', $this->request->status);
        $this->assertSame($administrator->id, $this->request->rejected_by);
        $this->assertNotNull($this->request->rejected_at);
        $this->assertSame('Insufficient details.', $this->request->rejection_reason);
    }

    public function test_administrator_can_convert_approved_request_to_work_order(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $request = $this->createRequest([
            'submitted_by' => $this->submitter->id,
            'severity' => 'Critical',
            'status' => 'Approved',
        ]);

        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRequests::class)
            ->callTableAction('convertToWorkOrder', $request)
            ->assertHasNoTableActionErrors();

        $request->refresh();

        $this->assertSame('Converted', $request->status);
        $this->assertSame($administrator->id, $request->converted_by);
        $this->assertNotNull($request->converted_at);
        $this->assertDatabaseHas('work_orders', [
            'maintenance_request_id' => $request->id,
            'equipment_id' => $request->equipment_id,
            'priority' => 'Critical',
            'status' => 'Available',
        ]);
    }

    public function test_duplicate_conversion_is_prevented(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $request = $this->createRequest([
            'submitted_by' => $this->submitter->id,
            'status' => 'Approved',
        ]);

        $request->createWorkOrder($administrator);

        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRequests::class)
            ->assertTableActionHidden('convertToWorkOrder', $request->fresh());

        $this->assertSame(1, WorkOrder::where('maintenance_request_id', $request->id)->count());
    }

    public function test_request_can_be_cancelled_before_approval(): void
    {
        $this->actingAs($this->submitter);

        Livewire::test(ListMaintenanceRequests::class)
            ->callTableAction('cancel', $this->request, data: [
                'remarks' => 'No longer needed.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Cancelled', $this->request->fresh()->status);
        $this->assertSame('No longer needed.', $this->request->fresh()->remarks);
    }

    public function test_approved_request_cannot_be_cancelled_by_submitter(): void
    {
        $request = $this->createRequest([
            'submitted_by' => $this->submitter->id,
            'status' => 'Approved',
        ]);

        $this->actingAs($this->submitter);

        Livewire::test(ListMaintenanceRequests::class)
            ->assertTableActionHidden('cancel', $request);
    }

    public function test_unauthorized_user_cannot_access_request_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/maintenance-requests')->assertForbidden();
    }

    public function test_request_table_searches_relationship_fields_safely(): void
    {
        $this->equipment->update(['equipment_name' => 'Request Search Microscope']);
        $this->submitter->update(['name' => 'Request Search Submitter']);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceRequests::class)
            ->assertCanSeeTableRecords([$this->request])
            ->searchTable('Request Search Microscope')
            ->assertCanSeeTableRecords([$this->request])
            ->searchTable('Request Search Submitter')
            ->assertCanSeeTableRecords([$this->request])
            ->searchTable('no matching request relationship term')
            ->assertCanNotSeeTableRecords([$this->request]);
    }

    public function test_request_resource_does_not_use_unsafe_relationship_search_arrays(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/MaintenanceRequests/MaintenanceRequestResource.php'));

        $this->assertStringNotContainsString("searchable(['equipment.", $resource);
        $this->assertStringNotContainsString("searchable(['submittedBy.", $resource);
    }

    private function createRequest(array $overrides = []): MaintenanceRequest
    {
        return MaintenanceRequest::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'submitted_by' => $this->submitter?->id ?? User::factory()->create()->id,
            'problem_description' => 'Resource request problem.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-REQ-'.uniqid(),
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
