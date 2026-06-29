<?php

namespace Tests\Feature\Equipment;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Equipment\Pages\CreateEquipment as CreateEquipmentPage;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentResourceTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentCategory $category;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->category = EquipmentCategory::create([
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'name' => 'Test Location',
            'type' => 'Room',
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_access_equipment_resource_pages(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment();

        $this->actingAs($administrator);

        $this->assertTrue(EquipmentResource::canViewAny());
        $this->get('/admin/equipment')->assertSuccessful();
        $this->get('/admin/equipment/create')->assertSuccessful();
        $this->get("/admin/equipment/{$equipment->id}/edit")->assertSuccessful();
    }

    public function test_staff_can_access_equipment_list_create_and_edit_pages(): void
    {
        $staff = $this->userWithRole('Staff');
        $equipment = $this->createEquipment();

        $this->actingAs($staff);

        $this->get('/admin/equipment')->assertSuccessful();
        $this->get('/admin/equipment/create')->assertSuccessful();
        $this->get("/admin/equipment/{$equipment->id}/edit")->assertSuccessful();
    }

    public function test_technician_can_access_list_but_not_create_or_edit_pages(): void
    {
        $technician = $this->userWithRole('Technician');
        $equipment = $this->createEquipment();

        $this->actingAs($technician);

        $this->get('/admin/equipment')->assertSuccessful();
        $this->get('/admin/equipment/create')->assertForbidden();
        $this->get("/admin/equipment/{$equipment->id}/edit")->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_equipment_list_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/admin/equipment')->assertForbidden();
    }

    public function test_administrator_can_create_equipment_with_required_fields(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->actingAs($administrator);

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData(['equipment_code' => 'EQ-FORM-001']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('equipment', [
            'equipment_code' => 'EQ-FORM-001',
            'equipment_name' => 'Form Equipment',
        ]);
    }

    public function test_equipment_code_must_be_unique(): void
    {
        $this->createEquipment(['equipment_code' => 'EQ-DUPLICATE']);
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData(['equipment_code' => 'EQ-DUPLICATE']))
            ->call('create')
            ->assertHasFormErrors(['equipment_code' => 'unique']);
    }

    public function test_required_equipment_fields_are_validated(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData([
                'equipment_name' => null,
                'equipment_category_id' => null,
                'current_location_id' => null,
                'condition' => null,
                'operational_status' => null,
            ]))
            ->call('create')
            ->assertHasFormErrors([
                'equipment_name' => 'required',
                'equipment_category_id' => 'required',
                'current_location_id' => 'required',
                'condition' => 'required',
                'operational_status' => 'required',
            ]);
    }

    public function test_acquisition_cost_cannot_be_negative(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData(['acquisition_cost' => -1]))
            ->call('create')
            ->assertHasFormErrors(['acquisition_cost' => 'min']);
    }

    public function test_staff_can_create_equipment_if_allowed_by_policy(): void
    {
        $staff = $this->userWithRole('Staff');

        $this->actingAs($staff);

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData(['equipment_code' => 'EQ-STAFF-001']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('equipment', ['equipment_code' => 'EQ-STAFF-001']);
    }

    public function test_staff_cannot_create_equipment_as_archived(): void
    {
        $staff = $this->userWithRole('Staff');

        $this->actingAs($staff);

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData([
                'equipment_code' => 'EQ-STAFF-ACTIVE-001',
                'is_archived' => true,
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('equipment', [
            'equipment_code' => 'EQ-STAFF-ACTIVE-001',
            'is_archived' => false,
        ]);
    }

    public function test_technician_cannot_create_equipment(): void
    {
        $technician = $this->userWithRole('Technician');

        $this->actingAs($technician);

        $this->get('/admin/equipment/create')->assertForbidden();
    }

    public function test_administrator_can_archive_equipment(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment();

        $this->actingAs($administrator);

        Livewire::test(ListEquipment::class)
            ->callTableAction('archive', $equipment)
            ->assertHasNoTableActionErrors();

        $equipment->refresh();

        $this->assertTrue($equipment->is_archived);
        $this->assertNotNull($equipment->archived_at);
        $this->assertTrue($equipment->archivedBy->is($administrator));
    }

    public function test_staff_and_technician_cannot_archive_equipment(): void
    {
        $equipment = $this->createEquipment();

        $this->assertFalse($this->userWithRole('Staff')->can('archive', $equipment));
        $this->assertFalse($this->userWithRole('Technician')->can('archive', $equipment));
    }

    public function test_archived_equipment_remains_in_database(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment(['equipment_code' => 'EQ-ARCHIVE-KEEP']);

        $this->actingAs($administrator);

        Livewire::test(ListEquipment::class)
            ->callTableAction('archive', $equipment);

        $this->assertDatabaseHas('equipment', [
            'equipment_code' => 'EQ-ARCHIVE-KEEP',
            'is_archived' => true,
        ]);
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
            'equipment_code' => 'EQ-TEST-'.uniqid(),
            'property_number' => 'PROP-001',
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $this->category->id,
            'current_location_id' => $this->location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }

    private function validEquipmentData(array $overrides = []): array
    {
        return array_merge([
            'equipment_code' => 'EQ-FORM-'.uniqid(),
            'property_number' => 'PROP-FORM',
            'equipment_name' => 'Form Equipment',
            'equipment_category_id' => $this->category->id,
            'description' => 'Test description',
            'brand' => 'Test Brand',
            'model' => 'Test Model',
            'serial_number' => 'SN-001',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 1000,
            'current_location_id' => $this->location->id,
            'custodian' => 'Test Custodian',
            'condition' => 'Good',
            'operational_status' => 'Available',
            'maintenance_frequency' => 'Monthly',
            'last_maintenance_date' => '2026-01-15',
            'next_maintenance_date' => '2026-02-15',
            'warranty_expiration_date' => '2027-01-01',
            'remarks' => 'Test remarks',
            'is_archived' => false,
        ], $overrides);
    }
}
