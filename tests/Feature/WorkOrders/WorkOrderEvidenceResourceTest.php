<?php

namespace Tests\Feature\WorkOrders;

use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\EvidencesRelationManager;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkOrderEvidenceResourceTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $administrator;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->administrator = $this->userWithRole('Administrator');
        $this->workOrder = $this->createWorkOrder();
    }

    public function test_authorized_user_can_upload_evidence_image(): void
    {
        $this->actingAs($this->administrator);

        Livewire::test(EvidencesRelationManager::class, [
            'ownerRecord' => $this->workOrder,
            'pageClass' => ViewWorkOrder::class,
        ])
            ->callTableAction('create', data: [
                'evidence_type' => 'Before maintenance',
                'image_path' => UploadedFile::fake()->image('before.jpg'),
                'caption' => 'Before repair image.',
            ])
            ->assertHasNoTableActionErrors();

        $evidence = WorkOrderEvidence::firstOrFail();

        $this->assertSame($this->workOrder->id, $evidence->work_order_id);
        $this->assertSame($this->workOrder->equipment_id, $evidence->equipment_id);
        $this->assertSame($this->administrator->id, $evidence->uploaded_by);
        $this->assertNotNull($evidence->uploaded_at);
        $this->assertSame('Before maintenance', $evidence->evidence_type);
        $this->assertSame('Before repair image.', $evidence->caption);
        $this->assertNotNull($evidence->image_path);
        Storage::disk('public')->assertExists($evidence->image_path);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->actingAs($this->administrator);

        Livewire::test(EvidencesRelationManager::class, [
            'ownerRecord' => $this->workOrder,
            'pageClass' => ViewWorkOrder::class,
        ])
            ->callTableAction('create', data: [
                'evidence_type' => 'Before maintenance',
                'image_path' => UploadedFile::fake()->create('manual.pdf', 20, 'application/pdf'),
                'caption' => 'Not an image.',
            ])
            ->assertHasTableActionErrors(['image_path']);
    }

    public function test_staff_can_upload_only_when_assigned(): void
    {
        $staff = $this->userWithRole('Staff');
        $assignedWorkOrder = $this->createWorkOrder(['assigned_to' => $staff->id]);
        $this->actingAs($staff);

        Livewire::test(EvidencesRelationManager::class, [
            'ownerRecord' => $assignedWorkOrder,
            'pageClass' => ViewWorkOrder::class,
        ])
            ->callTableAction('create', data: [
                'evidence_type' => 'During maintenance',
                'image_path' => UploadedFile::fake()->image('during.png'),
                'caption' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($staff->id, WorkOrderEvidence::firstOrFail()->uploaded_by);
    }

    public function test_upload_action_is_hidden_from_unauthorized_user(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EvidencesRelationManager::class, [
            'ownerRecord' => $this->workOrder,
            'pageClass' => ViewWorkOrder::class,
        ])
            ->assertTableActionHidden('create');
    }

    public function test_evidence_appears_on_work_order_view(): void
    {
        $evidence = WorkOrderEvidence::create([
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->workOrder->equipment_id,
            'evidence_type' => 'Inspection evidence',
            'image_path' => 'work-orders/evidence/inspection.jpg',
            'caption' => 'Visible evidence caption.',
            'uploaded_by' => $this->administrator->id,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->administrator);

        Livewire::test(ViewWorkOrder::class, ['record' => $this->workOrder->getRouteKey()])
            ->assertSee($evidence->evidence_type)
            ->assertSee('Visible evidence caption.');
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->administrator->id,
            'title' => 'Evidence upload work order',
            'problem_description' => 'Evidence upload problem.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-EV-'.uniqid(),
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
