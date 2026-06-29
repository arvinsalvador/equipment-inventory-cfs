<?php

namespace Tests\Feature\Equipment;

use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocationHistory;
use App\Models\Location;
use App\Models\User;
use App\Services\EquipmentQrCodeGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentQrCodeTest extends TestCase
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

    public function test_existing_equipment_can_receive_a_qr_identifier(): void
    {
        $equipment = $this->createEquipment();
        $equipment->forceFill(['qr_identifier' => null])->saveQuietly();

        app(EquipmentQrCodeGenerator::class)->generate($equipment);

        $this->assertNotNull($equipment->fresh()->qr_identifier);
    }

    public function test_new_equipment_automatically_receives_qr_identifier(): void
    {
        $equipment = $this->createEquipment();

        $this->assertNotNull($equipment->qr_identifier);
    }

    public function test_qr_identifier_is_unique(): void
    {
        $first = $this->createEquipment(['equipment_code' => 'EQ-QR-001']);
        $second = $this->createEquipment(['equipment_code' => 'EQ-QR-002']);

        $this->assertNotSame($first->qr_identifier, $second->qr_identifier);
    }

    public function test_qr_identifier_does_not_change_when_equipment_is_updated(): void
    {
        $equipment = $this->createEquipment();
        $identifier = $equipment->qr_identifier;

        $equipment->update(['equipment_name' => 'Updated Equipment']);

        $this->assertSame($identifier, $equipment->fresh()->qr_identifier);
    }

    public function test_qr_identifier_does_not_change_when_location_changes(): void
    {
        $staff = $this->userWithRole('Staff');
        $equipment = $this->createEquipment();
        $identifier = $equipment->qr_identifier;

        $this->actingAs($staff);

        Livewire::test(EditEquipment::class, ['record' => $equipment->getRouteKey()])
            ->fillForm([
                'current_location_id' => $this->newLocation->id,
                'location_transfer_remarks' => 'QR stability check.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($identifier, $equipment->fresh()->qr_identifier);
    }

    public function test_equipment_returns_valid_qr_lookup_url(): void
    {
        $equipment = $this->createEquipment();

        $this->assertStringContainsString('/equipment/lookup/', $equipment->getQrLookupUrl());
        $this->assertStringContainsString($equipment->qr_identifier, $equipment->getQrLookupUrl());
    }

    public function test_administrator_can_generate_qr_code(): void
    {
        $equipment = $this->createEquipment();
        $identifier = $equipment->qr_identifier;

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListEquipment::class)
            ->callTableAction('generateQrCode', $equipment)
            ->assertHasNoTableActionErrors();

        $equipment->refresh();

        $this->assertSame($identifier, $equipment->qr_identifier);
        $this->assertNotNull($equipment->qr_code_path);
        $this->assertNotNull($equipment->qr_code_generated_at);
        Storage::disk('public')->assertExists($equipment->qr_code_path);
    }

    public function test_staff_can_generate_qr_code_when_allowed_to_update_equipment(): void
    {
        $equipment = $this->createEquipment();

        $this->actingAs($this->userWithRole('Staff'));

        Livewire::test(ListEquipment::class)
            ->callTableAction('generateQrCode', $equipment)
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($equipment->fresh()->qr_code_path);
    }

    public function test_technician_cannot_generate_qr_code(): void
    {
        $equipment = $this->createEquipment();

        $this->actingAs($this->userWithRole('Technician'));

        Livewire::test(ListEquipment::class)
            ->assertTableActionHidden('generateQrCode', $equipment);
    }

    public function test_equipment_view_page_shows_qr_information(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment());

        $this->actingAs($this->userWithRole('Technician'));

        Livewire::test(ViewEquipment::class, ['record' => $equipment->getRouteKey()])
            ->assertSee($equipment->qr_identifier)
            ->assertSee('/equipment/lookup/'.$equipment->qr_identifier);
    }

    public function test_regression_existing_equipment_create_update_archive_and_location_history_still_work(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment(['equipment_code' => 'EQ-REGRESSION-001']);

        $this->actingAs($administrator);

        Livewire::test(EditEquipment::class, ['record' => $equipment->getRouteKey()])
            ->fillForm([
                'equipment_name' => 'Regression Updated',
                'current_location_id' => $this->newLocation->id,
                'location_transfer_remarks' => 'Regression transfer.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(ListEquipment::class)
            ->callTableAction('archive', $equipment)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('equipment', [
            'equipment_code' => 'EQ-REGRESSION-001',
            'equipment_name' => 'Regression Updated',
            'is_archived' => true,
        ]);

        $this->assertSame(1, EquipmentLocationHistory::count());
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
}
