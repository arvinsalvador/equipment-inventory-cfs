<?php

namespace Tests\Feature\Equipment;

use App\Filament\Resources\Equipment\Pages\CreateEquipment as CreateEquipmentPage;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocationHistory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentPhotoAndLocationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentCategory $category;

    private Location $location;

    private Location $newLocation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $this->category = EquipmentCategory::create([
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'name' => 'Original Room',
            'type' => 'Room',
            'is_active' => true,
        ]);

        $this->newLocation = Location::create([
            'name' => 'New Room',
            'type' => 'Room',
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_upload_an_equipment_photo(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData([
                'equipment_code' => 'EQ-PHOTO-001',
                'photo_path' => UploadedFile::fake()->image('equipment.jpg'),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $equipment = Equipment::where('equipment_code', 'EQ-PHOTO-001')->firstOrFail();

        $this->assertNotNull($equipment->photo_path);
        Storage::disk('public')->assertExists($equipment->photo_path);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(CreateEquipmentPage::class)
            ->fillForm($this->validEquipmentData([
                'photo_path' => UploadedFile::fake()->create('manual.pdf', 20, 'application/pdf'),
            ]))
            ->call('create')
            ->assertHasFormErrors(['photo_path']);
    }

    public function test_staff_can_update_equipment_photo_when_authorized_to_update(): void
    {
        $equipment = $this->createEquipment();
        $this->actingAs($this->userWithRole('Staff'));

        Livewire::test(EditEquipment::class, ['record' => $equipment->getRouteKey()])
            ->fillForm([
                'photo_path' => UploadedFile::fake()->image('staff-photo.png'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $equipment->refresh();

        $this->assertNotNull($equipment->photo_path);
        Storage::disk('public')->assertExists($equipment->photo_path);
    }

    public function test_technician_cannot_upload_or_update_equipment_photo(): void
    {
        $equipment = $this->createEquipment();
        $this->actingAs($this->userWithRole('Technician'));

        $this->get("/admin/equipment/{$equipment->id}/edit")->assertForbidden();
    }

    public function test_updating_equipment_location_creates_location_history(): void
    {
        $staff = $this->userWithRole('Staff');
        $equipment = $this->createEquipment();

        $this->actingAs($staff);

        Livewire::test(EditEquipment::class, ['record' => $equipment->getRouteKey()])
            ->fillForm([
                'current_location_id' => $this->newLocation->id,
                'location_transfer_remarks' => 'Moved to the new room.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $history = EquipmentLocationHistory::first();

        $this->assertNotNull($history);
        $this->assertSame($equipment->id, $history->equipment_id);
        $this->assertSame($this->location->id, $history->from_location_id);
        $this->assertSame($this->newLocation->id, $history->to_location_id);
        $this->assertSame($staff->id, $history->transferred_by);
        $this->assertNotNull($history->transferred_at);
        $this->assertSame('Moved to the new room.', $history->remarks);
    }

    public function test_updating_equipment_without_location_change_does_not_create_history(): void
    {
        $equipment = $this->createEquipment();
        $this->actingAs($this->userWithRole('Staff'));

        Livewire::test(EditEquipment::class, ['record' => $equipment->getRouteKey()])
            ->fillForm([
                'equipment_name' => 'Updated Name',
                'current_location_id' => $this->location->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, EquipmentLocationHistory::count());
    }

    public function test_technician_cannot_change_equipment_location(): void
    {
        $equipment = $this->createEquipment();
        $this->actingAs($this->userWithRole('Technician'));

        $this->get("/admin/equipment/{$equipment->id}/edit")->assertForbidden();
        $this->assertSame($this->location->id, $equipment->fresh()->current_location_id);
    }

    public function test_equipment_has_location_histories_relationship(): void
    {
        $equipment = $this->createEquipment();

        $history = EquipmentLocationHistory::create([
            'equipment_id' => $equipment->id,
            'from_location_id' => $this->location->id,
            'to_location_id' => $this->newLocation->id,
            'transferred_at' => now(),
        ]);

        $this->assertTrue($equipment->locationHistories->contains($history));
    }

    public function test_existing_archive_action_still_works(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment();

        $this->actingAs($administrator);

        Livewire::test(ListEquipment::class)
            ->callTableAction('archive', $equipment)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($equipment->fresh()->is_archived);
    }

    public function test_existing_equipment_resource_access_rules_still_work(): void
    {
        $equipment = $this->createEquipment();

        $this->actingAs($this->userWithRole('Staff'));
        $this->get('/admin/equipment')->assertSuccessful();
        $this->get("/admin/equipment/{$equipment->id}/edit")->assertSuccessful();

        $this->actingAs($this->userWithRole('Technician'));
        $this->get('/admin/equipment')->assertSuccessful();
        $this->get("/admin/equipment/{$equipment->id}/edit")->assertForbidden();
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
