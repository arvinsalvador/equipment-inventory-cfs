<?php

namespace Tests\Feature\Equipment;

use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentPropertyCardTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentCategory $category;

    private Location $location;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->category = EquipmentCategory::create([
            'account_code' => '10605110',
            'name' => 'Medical, Dental & Laboratory Equipment',
            'is_active' => true,
        ]);
        $this->location = Location::create([
            'name' => 'Clinic',
            'type' => 'Room',
            'is_active' => true,
        ]);
    }

    public function test_equipment_view_page_shows_property_card_actions(): void
    {
        $equipment = $this->createEquipment();

        $this->actingAs($this->administrator)
            ->get("/admin/equipment/{$equipment->id}")
            ->assertOk()
            ->assertSee('Edit Equipment')
            ->assertSee('View Property Card')
            ->assertSee('Open QR Lookup')
            ->assertSee('More Actions')
            ->assertSee('Print Card')
            ->assertSee('Download PDF')
            ->assertSee('Open Card in New Tab')
            ->assertDontSee('Print Property Card')
            ->assertDontSee('Regenerate QR Code')
            ->assertDontSee('Open QR Code File');
    }

    public function test_equipment_view_header_groups_secondary_actions_without_removing_them(): void
    {
        Storage::disk('public')->put('equipment/qr-codes/grouped-qr.png', 'qr');
        $equipment = $this->createEquipment([
            'qr_code_path' => 'equipment/qr-codes/grouped-qr.png',
        ]);

        $this->actingAs($this->administrator);

        Livewire::test(ViewEquipment::class, ['record' => $equipment->getRouteKey()])
            ->assertActionVisible('edit')
            ->assertActionHasLabel('edit', 'Edit Equipment')
            ->assertActionVisible('viewPropertyCard')
            ->assertActionVisible('openQrLookup')
            ->assertActionVisible('printPropertyCard')
            ->assertActionHasLabel('printPropertyCard', 'Print Card')
            ->assertActionVisible('downloadPropertyCard')
            ->assertActionVisible('openPropertyCard')
            ->assertActionHasLabel('openPropertyCard', 'Open Card in New Tab')
            ->assertActionVisible('openQrCodeFile')
            ->assertActionHasLabel('openQrCodeFile', 'View QR File')
            ->assertActionVisible('generateQrCode')
            ->assertActionHasLabel('generateQrCode', 'Regenerate QR')
            ->assertActionVisible('recalculateLifecycle')
            ->assertActionVisible('createAssetActionRequest');
    }

    public function test_property_card_preview_renders_equipment_fields_and_photo(): void
    {
        Storage::disk('public')->put('equipment/photos/card-photo.jpg', 'photo');
        Storage::disk('public')->put('equipment/qr-codes/card-qr.png', 'qr');

        $equipment = $this->createEquipment([
            'property_number' => '25-03-MDLE-12DC-3',
            'equipment_name' => 'Dental Chair With A Very Long Name That Should Wrap Cleanly',
            'description' => 'Clinic dental equipment.',
            'serial_number' => 'SN-12345',
            'acquisition_date' => '2025-03-03',
            'acquisition_cost' => 250000,
            'photo_path' => 'equipment/photos/card-photo.jpg',
            'qr_code_path' => 'equipment/qr-codes/card-qr.png',
        ]);

        $this->actingAs($this->administrator)
            ->get(route('equipment.property-card.show', $equipment))
            ->assertOk()
            ->assertSee('Surigao del Norte State University-Del Carmen Campus')
            ->assertSee('Property Number')
            ->assertSee('25-03-MDLE-12DC-3')
            ->assertSee('SN-12345')
            ->assertSee('PHP 250,000.00')
            ->assertSee('March 3, 2025')
            ->assertSee('Clinic')
            ->assertSee('Dental Chair With A Very Long Name That Should Wrap Cleanly')
            ->assertSee('10605110')
            ->assertSee('/storage/equipment/photos/card-photo.jpg', false)
            ->assertSee('/storage/equipment/qr-codes/card-qr.png', false)
            ->assertSee('object-fit: contain', false)
            ->assertSee('overflow-wrap: anywhere', false)
            ->assertDontSee('Equipment<br>Image<br>Here', false)
            ->assertDontSee('storage/app/public');
    }

    public function test_property_card_uses_placeholder_and_not_recorded_for_missing_values(): void
    {
        $equipment = $this->createEquipment([
            'serial_number' => null,
            'photo_path' => null,
            'acquisition_cost' => null,
            'acquisition_date' => null,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('equipment.property-card.show', $equipment))
            ->assertOk()
            ->assertSee('Equipment<br>Image<br>Here', false)
            ->assertSee('Not recorded')
            ->assertDontSee('<img src=""', false);
    }

    public function test_property_card_print_and_pdf_routes_are_authorized(): void
    {
        $equipment = $this->createEquipment();

        $this->actingAs($this->administrator)
            ->get(route('equipment.property-card.print', $equipment))
            ->assertOk()
            ->assertSee('window.print', false);

        $this->actingAs($this->administrator)
            ->get(route('equipment.property-card.pdf', $equipment))
            ->assertOk()
            ->assertSee('Equipment Property Card');

        $this->actingAs(User::factory()->create())
            ->get(route('equipment.property-card.show', $equipment))
            ->assertForbidden();
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-CARD-'.uniqid(),
            'property_number' => 'PROP-CARD',
            'equipment_name' => 'Property Card Equipment',
            'equipment_category_id' => $this->category->id,
            'current_location_id' => $this->location->id,
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
