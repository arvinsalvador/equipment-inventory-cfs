<?php

namespace Tests\Feature\Equipment;

use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
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

class EquipmentQrLookupAndScannerTest extends TestCase
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

    public function test_guest_is_redirected_from_equipment_lookup(): void
    {
        $equipment = $this->createEquipment();

        $this->get(route('equipment.lookup', $equipment->qr_identifier))
            ->assertRedirect('/admin/login');
    }

    public function test_user_without_equipment_view_permission_cannot_access_lookup(): void
    {
        $equipment = $this->createEquipment();

        $this->actingAs(User::factory()->create());

        $this->get(route('equipment.lookup', $equipment->qr_identifier))
            ->assertForbidden();
    }

    public function test_authorized_roles_can_lookup_equipment_by_qr_identifier(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment([
            'equipment_code' => 'EQ-LOOKUP-001',
            'property_number' => 'PROP-LOOKUP-001',
            'equipment_name' => 'Lookup Weather Console',
            'last_maintenance_date' => '2026-01-10',
            'next_maintenance_date' => '2026-02-10',
            'warranty_expiration_date' => '2027-01-10',
            'remarks' => 'Lookup remarks.',
        ]));

        foreach (['Administrator', 'Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('equipment.lookup', $equipment->qr_identifier))
                ->assertOk()
                ->assertSee('Lookup Weather Console')
                ->assertSee('EQ-LOOKUP-001')
                ->assertSee('PROP-LOOKUP-001')
                ->assertSee('Test Category')
                ->assertSee('Original Room')
                ->assertSee('Good')
                ->assertSee('Available')
                ->assertSee('Lookup remarks.')
                ->assertSee('QR generated date/time')
                ->assertDontSee('Equipment ID')
                ->assertDontSee('Database ID');
        }
    }

    public function test_invalid_lookup_identifier_shows_friendly_not_found_page(): void
    {
        $this->actingAs($this->userWithRole('Staff'));

        $this->get(route('equipment.lookup', 'missing-qr-identifier'))
            ->assertOk()
            ->assertSee('Equipment not found')
            ->assertSee('No equipment record matches this QR code');
    }

    public function test_guest_is_redirected_from_scanner_page(): void
    {
        $this->get(route('equipment.scan'))
            ->assertRedirect('/admin/login');
    }

    public function test_user_without_equipment_view_permission_cannot_access_scanner_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('equipment.scan'))
            ->assertForbidden();
    }

    public function test_authorized_roles_can_access_scanner_with_manual_fallback(): void
    {
        foreach (['Administrator', 'Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('equipment.scan'))
                ->assertOk()
                ->assertSee('Start camera scanner')
                ->assertSee('Manual QR identifier or lookup URL')
                ->assertSee('Camera scanning requires HTTPS or localhost')
                ->assertSee('Open equipment');
        }
    }

    public function test_manual_scanner_fallback_redirects_to_lookup_by_identifier(): void
    {
        $equipment = $this->createEquipment();
        $this->actingAs($this->userWithRole('Technician'));

        $this->post(route('equipment.scan.manual'), [
            'qr_identifier' => $equipment->qr_identifier,
        ])->assertRedirect(route('equipment.lookup', $equipment->qr_identifier));
    }

    public function test_manual_scanner_fallback_accepts_lookup_url(): void
    {
        $equipment = $this->createEquipment();
        $this->actingAs($this->userWithRole('Staff'));

        $this->post(route('equipment.scan.manual'), [
            'qr_identifier' => route('equipment.lookup', $equipment->qr_identifier),
        ])->assertRedirect(route('equipment.lookup', $equipment->qr_identifier));
    }

    public function test_authorized_users_can_see_qr_lookup_action(): void
    {
        $equipment = $this->createEquipment();

        foreach (['Administrator', 'Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            Livewire::test(ListEquipment::class)
                ->assertTableActionVisible('openQrLookup', $equipment);
        }
    }

    public function test_technician_can_view_qr_actions_but_cannot_generate_qr_code(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment());
        $this->actingAs($this->userWithRole('Technician'));

        Livewire::test(ListEquipment::class)
            ->assertTableActionVisible('openQrLookup', $equipment)
            ->assertTableActionVisible('openQrCodeFile', $equipment)
            ->assertTableActionHidden('generateQrCode', $equipment);
    }

    public function test_generate_qr_code_and_archive_actions_still_work(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment();

        $this->actingAs($administrator);

        Livewire::test(ListEquipment::class)
            ->callTableAction('generateQrCode', $equipment)
            ->assertHasNoTableActionErrors();

        $equipment->refresh();
        $this->assertNotNull($equipment->qr_code_path);
        Storage::disk('public')->assertExists($equipment->qr_code_path);

        Livewire::test(ListEquipment::class)
            ->callTableAction('archive', $equipment)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($equipment->fresh()->is_archived);
    }

    public function test_equipment_update_qr_generation_and_location_history_still_work(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment(['equipment_code' => 'EQ-4B-REGRESSION']);
        $identifier = $equipment->qr_identifier;

        $this->actingAs($administrator);

        Livewire::test(EditEquipment::class, ['record' => $equipment->getRouteKey()])
            ->fillForm([
                'equipment_name' => 'Phase 4B Updated Equipment',
                'current_location_id' => $this->newLocation->id,
                'location_transfer_remarks' => 'Phase 4B transfer regression.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $equipment = app(EquipmentQrCodeGenerator::class)->generate($equipment->fresh());

        $this->assertSame($identifier, $equipment->qr_identifier);
        $this->assertNotNull($equipment->qr_code_path);
        $this->assertSame(1, EquipmentLocationHistory::count());
    }

    public function test_staff_and_technician_do_not_have_equipment_permanent_delete_permissions(): void
    {
        $equipment = $this->createEquipment();

        $this->assertFalse($this->userWithRole('Staff')->can('forceDelete', $equipment));
        $this->assertFalse($this->userWithRole('Technician')->can('forceDelete', $equipment));
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
