<?php

namespace Tests\Feature\AssetActions;

use App\Filament\Resources\AssetActionRequests\AssetActionRequestResource;
use App\Filament\Resources\AssetActionRequests\Pages\CreateAssetActionRequest;
use App\Filament\Resources\AssetActionRequests\Pages\ListAssetActionRequests;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Models\AssetActionRequest;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetActionRequestTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->equipment = $this->createEquipment();
    }

    public function test_asset_action_request_model_relationships_numbers_cost_and_transitions(): void
    {
        $request = $this->createAssetActionRequest([
            'estimated_cost' => 12500.50,
        ]);

        $this->assertStringStartsWith('AAR-'.now()->format('Ymd').'-', $request->request_number);
        $this->assertTrue($request->equipment->is($this->equipment));
        $this->assertTrue($request->requestedBy->is($this->administrator));
        $this->assertSame('12500.50', $request->estimated_cost);
        $this->assertTrue($request->isPending());
        $this->assertFalse($request->isApproved());

        $request->submit();
        $this->assertSame('Submitted', $request->status);
        $this->assertSame(1, AssetActionRequest::submitted()->count());

        $request->markUnderReview($this->administrator);
        $this->assertSame('Under Review', $request->status);
        $this->assertTrue($request->reviewedBy->is($this->administrator));
        $this->assertNotNull($request->reviewed_at);

        $request->approve($this->administrator);
        $this->assertTrue($request->isApproved());
        $this->assertTrue($request->approvedBy->is($this->administrator));
        $this->assertNotNull($request->approved_at);

        $request->complete($this->administrator, 'Replacement tracked.');
        $this->assertTrue($request->isCompleted());
        $this->assertTrue($request->completedBy->is($this->administrator));
        $this->assertSame('Replacement tracked.', $request->remarks);
        $this->assertSame(1, AssetActionRequest::completed()->count());
    }

    public function test_reject_and_cancel_transitions_work(): void
    {
        $reject = $this->createAssetActionRequest(['status' => 'Submitted']);
        $reject->reject($this->administrator, 'Not justified.');

        $this->assertSame('Rejected', $reject->status);
        $this->assertSame('Not justified.', $reject->rejection_reason);
        $this->assertSame(1, AssetActionRequest::rejected()->count());

        $cancel = $this->createAssetActionRequest();
        $cancel->cancel('Duplicate request.');

        $this->assertSame('Cancelled', $cancel->status);
        $this->assertSame('Duplicate request.', $cancel->cancellation_reason);
    }

    public function test_asset_action_request_policy_rules(): void
    {
        $staff = $this->userWithRole('Staff');
        $technician = $this->userWithRole('Technician');
        $plainUser = User::factory()->create();
        $request = $this->createAssetActionRequest(['status' => 'Under Review']);

        $this->assertTrue($this->administrator->can('viewAny', AssetActionRequest::class));
        $this->assertTrue($this->administrator->can('create', AssetActionRequest::class));
        $this->assertTrue($this->administrator->can('review', $this->createAssetActionRequest(['status' => 'Submitted'])));
        $this->assertTrue($this->administrator->can('approve', $request));
        $this->assertFalse($staff->can('approve', $request));
        $this->assertFalse($technician->can('approve', $request));
        $this->assertFalse($plainUser->can('viewAny', AssetActionRequest::class));
        $this->assertFalse($this->administrator->can('delete', $request));
        $this->assertFalse($this->administrator->can('forceDelete', $request));
    }

    public function test_filament_resource_pages_create_and_workflow_actions(): void
    {
        $this->actingAs($this->administrator);

        $this->get(AssetActionRequestResource::getUrl('index'))->assertOk();
        $this->get(AssetActionRequestResource::getUrl('create'))->assertOk();

        Livewire::test(CreateAssetActionRequest::class)
            ->fillForm([
                'equipment_id' => $this->equipment->id,
                'request_type' => 'Replacement',
                'reason' => 'Lifecycle replacement candidate.',
                'justification' => 'Health score is critical.',
                'recommended_action' => 'Replace equipment.',
                'estimated_cost' => 25000,
                'priority' => 'Critical',
                'remarks' => 'Phase 13A test.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = AssetActionRequest::latest('id')->first();

        $this->assertSame('Draft', $request->status);
        $this->assertSame($this->administrator->id, $request->requested_by);

        Livewire::test(ListAssetActionRequests::class)
            ->callTableAction('submit', $request)
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame('Submitted', $request->status);

        Livewire::test(ListAssetActionRequests::class)
            ->callTableAction('markUnderReview', $request)
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame('Under Review', $request->status);

        Livewire::test(ListAssetActionRequests::class)
            ->callTableAction('approve', $request)
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame('Approved', $request->status);

        Livewire::test(ListAssetActionRequests::class)
            ->callTableAction('complete', $request, data: [
                'remarks' => 'Completed from table.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Completed', $request->fresh()->status);
        $this->assertSame('Completed from table.', $request->fresh()->remarks);
    }

    public function test_filament_reject_action_and_invalid_actions_are_hidden(): void
    {
        $this->actingAs($this->administrator);
        $submitted = $this->createAssetActionRequest(['status' => 'Submitted']);
        $completed = $this->createAssetActionRequest(['status' => 'Completed']);

        Livewire::test(ListAssetActionRequests::class)
            ->callTableAction('reject', $submitted, data: [
                'rejection_reason' => 'Rejected from table.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Rejected', $submitted->fresh()->status);
        $this->assertSame('Rejected from table.', $submitted->fresh()->rejection_reason);

        Livewire::test(ListAssetActionRequests::class)
            ->assertTableActionHidden('approve', $completed)
            ->assertTableActionHidden('submit', $completed)
            ->assertTableActionHidden('complete', $completed);
    }

    public function test_staff_technician_and_unauthorized_users_cannot_access_resource(): void
    {
        $this->actingAs($this->userWithRole('Staff'))
            ->get(AssetActionRequestResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('Technician'))
            ->get(AssetActionRequestResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(AssetActionRequestResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_equipment_view_shows_related_asset_action_requests(): void
    {
        $request = $this->createAssetActionRequest([
            'request_type' => 'Disposal',
            'reason' => 'Beyond repair.',
        ]);

        $this->actingAs($this->administrator);

        Livewire::test(ViewEquipment::class, ['record' => $this->equipment->getRouteKey()])
            ->assertSee('Asset action requests')
            ->assertSee($request->request_number)
            ->assertSee('Disposal')
            ->assertActionVisible('createAssetActionRequest');
    }

    public function test_audit_logs_are_created_for_approval_rejection_and_completion(): void
    {
        $this->actingAs($this->administrator);

        $approved = $this->createAssetActionRequest(['status' => 'Submitted']);
        $approved->approve($this->administrator);
        $approved->complete($this->administrator);

        $rejected = $this->createAssetActionRequest(['status' => 'Submitted']);
        $rejected->reject($this->administrator, 'No longer needed.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approved',
            'module' => 'Asset Action Request',
            'entity_id' => $approved->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'completed',
            'module' => 'Asset Action Request',
            'entity_id' => $approved->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rejected',
            'module' => 'Asset Action Request',
            'entity_id' => $rejected->id,
        ]);
    }

    public function test_asset_action_request_report_renders_csv_and_print(): void
    {
        $request = $this->createAssetActionRequest([
            'request_type' => 'Procurement',
            'priority' => 'High',
            'status' => 'Approved',
            'estimated_cost' => 9900,
        ]);

        $this->actingAs($this->administrator)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Asset Action Request Report');

        $csv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', ['report' => 'asset-action-requests', 'request_type' => 'Procurement']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Request number', $csv);
        $this->assertStringContainsString($request->request_number, $csv);

        $this->actingAs($this->administrator)
            ->get(route('reports.print', 'asset-action-requests'))
            ->assertOk()
            ->assertSee('Asset Action Request Report')
            ->assertSee($request->request_number);
    }

    private function createAssetActionRequest(array $overrides = []): AssetActionRequest
    {
        return AssetActionRequest::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'requested_by' => $this->administrator->id,
            'request_type' => 'Replacement',
            'reason' => 'Lifecycle analysis recommends replacement.',
            'justification' => 'Health score and maintenance history indicate replacement.',
            'recommended_action' => 'Replace equipment.',
            'estimated_cost' => 12000,
            'priority' => 'High',
            'status' => 'Draft',
        ], $overrides));
    }

    private function createEquipment(): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Asset Action Category']);
        $location = Location::create(['name' => 'Asset Action Room', 'type' => 'Room']);

        return Equipment::create([
            'equipment_code' => 'EQ-AAR-'.uniqid(),
            'equipment_name' => 'Asset Action Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Beyond repair',
            'operational_status' => 'Unavailable',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
