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
        $this->assertStringStartsWith('equipment/qr-codes/', $equipment->qr_code_path);
        $this->assertStringEndsWith('.png', $equipment->qr_code_path);
        $this->assertSame("equipment/qr-codes/{$equipment->qr_identifier}.png", $equipment->qr_code_path);
        $this->assertStringNotContainsString('storage/app/public', $equipment->qr_code_path);
        $this->assertNotNull($equipment->qr_code_generated_at);
        Storage::disk('public')->assertExists($equipment->qr_code_path);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr(Storage::disk('public')->get($equipment->qr_code_path), 0, 8));
        $this->assertNotEmpty($equipment->getQrCodeUrl());
        $this->assertSame(url("/storage/equipment/qr-codes/{$equipment->qr_identifier}.png"), $equipment->getQrCodeUrl());
        $this->assertStringContainsString('/storage/equipment/qr-codes/', $equipment->getQrCodeUrl());
    }

    public function test_generated_qr_code_uses_public_storage_visibility(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment());

        Storage::disk('public')->assertExists($equipment->qr_code_path);
        $this->assertStringEndsWith('.png', $equipment->qr_code_path);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr(Storage::disk('public')->get($equipment->qr_code_path), 0, 8));
        $this->assertSame('public', Storage::disk('public')->getVisibility($equipment->qr_code_path));
        $this->assertStringContainsString('/storage/equipment/qr-codes/', $equipment->getQrCodeUrl());
        $this->assertStringNotContainsString('storage/app/public', $equipment->getQrCodeUrl());
    }

    public function test_qr_code_url_normalizes_existing_public_storage_paths(): void
    {
        Storage::disk('public')->put('equipment/qr-codes/legacy.png', '<svg></svg>', [
            'visibility' => 'public',
        ]);

        foreach ([
            'equipment/qr-codes/legacy.png',
            'storage/equipment/qr-codes/legacy.png',
            '/storage/equipment/qr-codes/legacy.png',
            'public/storage/equipment/qr-codes/legacy.png',
            'storage/app/public/equipment/qr-codes/legacy.png',
            'http://localhost:8087/storage/equipment/qr-codes/legacy.png',
        ] as $path) {
            $equipment = $this->createEquipment([
                'equipment_code' => 'EQ-QR-LEGACY-'.md5($path),
                'qr_code_path' => $path,
            ]);

            $this->assertSame('equipment/qr-codes/legacy.png', $equipment->normalized_qr_code_path);
            $this->assertSame(url('/storage/equipment/qr-codes/legacy.png'), $equipment->getQrCodeUrl());
            $this->assertStringContainsString('/storage/equipment/qr-codes/legacy.png', $equipment->getQrCodeUrl());
            $this->assertStringNotContainsString('storage/app/public', $equipment->getQrCodeUrl());
        }
    }

    public function test_qr_code_url_returns_null_when_path_is_empty_or_file_is_missing(): void
    {
        $equipment = $this->createEquipment(['qr_code_path' => null]);

        $this->assertNull($equipment->qr_code_url);

        $equipment->forceFill(['qr_code_path' => 'equipment/qr-codes/missing.png'])->save();

        $this->assertNull($equipment->fresh()->qr_code_url);
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
            ->assertSee('QR code')
            ->assertSee($equipment->qr_identifier)
            ->assertSee('/equipment/lookup/'.$equipment->qr_identifier)
            ->assertSee($equipment->qr_code_url, false)
            ->assertDontSee('storage/app/public')
            ->assertSee('storage/equipment/qr-codes', false);
    }

    public function test_equipment_list_page_renders_qr_image_from_public_url(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment());

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListEquipment::class)
            ->assertSee($equipment->qr_code_url, false)
            ->assertSee('storage/equipment/qr-codes', false);
    }

    public function test_lookup_page_displays_qr_image_when_available(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment());

        $this->actingAs($this->userWithRole('Technician'));

        $this->get(route('equipment.lookup', $equipment->qr_identifier))
            ->assertOk()
            ->assertSee('QR code')
            ->assertSee('Equipment QR code', false)
            ->assertSee($equipment->getQrCodeUrl(), false)
            ->assertDontSee('storage/app/public')
            ->assertSee('storage/equipment/qr-codes', false)
            ->assertDontSee('QR code not generated');
    }

    public function test_qr_code_url_is_empty_when_saved_file_is_missing(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment());
        Storage::disk('public')->delete($equipment->qr_code_path);

        $this->assertNull($equipment->fresh()->getQrCodeUrl());
    }

    public function test_regenerating_qr_code_updates_generated_timestamp_without_changing_identifier(): void
    {
        $equipment = app(EquipmentQrCodeGenerator::class)->generate($this->createEquipment());
        $identifier = $equipment->qr_identifier;
        $path = $equipment->qr_code_path;
        $generatedAt = $equipment->qr_code_generated_at;

        $equipment->forceFill(['qr_code_generated_at' => now()->subDay()])->save();

        $regenerated = app(EquipmentQrCodeGenerator::class)->generate($equipment->fresh());

        $this->assertSame($identifier, $regenerated->qr_identifier);
        $this->assertSame($path, $regenerated->qr_code_path);
        $this->assertTrue($regenerated->qr_code_generated_at->greaterThan($generatedAt->subMinute()));
        Storage::disk('public')->assertExists($regenerated->qr_code_path);
    }

    public function test_regenerating_legacy_svg_qr_replaces_it_with_png(): void
    {
        $equipment = $this->createEquipment();
        $legacyPath = "equipment/qr-codes/{$equipment->qr_identifier}.svg";

        Storage::disk('public')->put($legacyPath, 'not valid svg binary content', [
            'visibility' => 'public',
        ]);

        $equipment->forceFill([
            'qr_code_path' => $legacyPath,
            'qr_code_generated_at' => now()->subDay(),
        ])->save();

        $regenerated = app(EquipmentQrCodeGenerator::class)->generate($equipment->fresh());

        $this->assertSame("equipment/qr-codes/{$equipment->qr_identifier}.png", $regenerated->qr_code_path);
        Storage::disk('public')->assertMissing($legacyPath);
        Storage::disk('public')->assertExists($regenerated->qr_code_path);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr(Storage::disk('public')->get($regenerated->qr_code_path), 0, 8));
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
