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
        $this->assertStringStartsWith('equipment/photos/', $equipment->photo_path);
        Storage::disk('public')->assertExists($equipment->photo_path);
        $this->assertStringStartsWith('/storage/equipment/photos/', $equipment->getPhotoUrl());
        $this->assertStringContainsString('/storage/equipment/photos/', $equipment->getPhotoUrl());
        $this->assertFalse(str_starts_with($equipment->getPhotoUrl(), 'http://localhost/storage/'));
        $this->assertStringNotContainsString('storage/app/public', $equipment->getPhotoUrl());
    }

    public function test_equipment_photo_url_normalizes_existing_public_storage_paths(): void
    {
        Storage::disk('public')->put('equipment/photos/legacy-photo.jpg', 'photo');

        foreach ([
            'equipment/photos/legacy-photo.jpg',
            'storage/equipment/photos/legacy-photo.jpg',
            '/storage/equipment/photos/legacy-photo.jpg',
            'public/storage/equipment/photos/legacy-photo.jpg',
            'storage/app/public/equipment/photos/legacy-photo.jpg',
            'http://localhost:8087/storage/equipment/photos/legacy-photo.jpg',
        ] as $path) {
            $equipment = $this->createEquipment([
                'equipment_code' => 'EQ-LEGACY-'.md5($path),
                'photo_path' => $path,
            ]);

            $this->assertSame('equipment/photos/legacy-photo.jpg', $equipment->normalized_photo_path);
            $this->assertSame('/storage/equipment/photos/legacy-photo.jpg', $equipment->getPhotoUrl());
            $this->assertStringContainsString('/storage/equipment/photos/legacy-photo.jpg', $equipment->getPhotoUrl());
            $this->assertStringNotContainsString('storage/app/public', $equipment->getPhotoUrl());
        }
    }

    public function test_equipment_photo_url_returns_null_when_path_is_empty_or_file_is_missing(): void
    {
        $equipment = $this->createEquipment(['photo_path' => null]);

        $this->assertNull($equipment->equipment_photo_url);

        $equipment->forceFill(['photo_path' => 'equipment/photos/missing.jpg'])->save();

        $this->assertNull($equipment->fresh()->equipment_photo_url);
    }

    public function test_equipment_list_renders_photo_public_url(): void
    {
        Storage::disk('public')->put('equipment/photos/list-photo.jpg', 'photo');
        $equipment = $this->createEquipment([
            'photo_path' => 'storage/equipment/photos/list-photo.jpg',
        ]);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListEquipment::class)
            ->assertSee($equipment->equipment_code)
            ->assertSee('/storage/equipment/photos/list-photo.jpg', false)
            ->assertDontSee('storage/app/public')
            ->assertDontSee('http://localhost/storage');
    }

    public function test_equipment_view_renders_photo_public_url(): void
    {
        Storage::disk('public')->put('equipment/photos/view-photo.jpg', 'photo');
        $equipment = $this->createEquipment([
            'photo_path' => '/storage/equipment/photos/view-photo.jpg',
        ]);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(\App\Filament\Resources\Equipment\Pages\ViewEquipment::class, ['record' => $equipment->getRouteKey()])
            ->assertSee('Equipment photo')
            ->assertSee('/storage/equipment/photos/view-photo.jpg', false)
            ->assertDontSee('storage/app/public')
            ->assertDontSee('http://localhost/storage');
    }

    public function test_qr_lookup_page_renders_equipment_photo_public_url(): void
    {
        Storage::disk('public')->put('equipment/photos/lookup-photo.jpg', 'photo');
        $equipment = $this->createEquipment([
            'photo_path' => 'storage/equipment/photos/lookup-photo.jpg',
        ]);

        $this->actingAs($this->userWithRole('Technician'));

        $this->get(route('equipment.lookup', $equipment->qr_identifier))
            ->assertOk()
            ->assertSee('/storage/equipment/photos/lookup-photo.jpg', false)
            ->assertDontSee('storage/app/public')
            ->assertDontSee('http://localhost/storage');
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
