<?php

namespace Tests\Feature\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocationHistory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_table_accepts_required_fields(): void
    {
        $equipment = $this->createEquipment();

        $this->assertDatabaseHas('equipment', [
            'id' => $equipment->id,
            'equipment_code' => 'EQ-TEST-001',
            'equipment_name' => 'Test Equipment',
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);
    }

    public function test_equipment_code_is_unique(): void
    {
        $this->createEquipment(['equipment_code' => 'EQ-UNIQUE-001']);

        $this->expectException(QueryException::class);

        $this->createEquipment(['equipment_code' => 'EQ-UNIQUE-001']);
    }

    public function test_equipment_belongs_to_category(): void
    {
        $equipment = $this->createEquipment();

        $this->assertTrue($equipment->category->is($equipment->category()->first()));
        $this->assertInstanceOf(EquipmentCategory::class, $equipment->category);
    }

    public function test_equipment_belongs_to_current_location(): void
    {
        $equipment = $this->createEquipment();

        $this->assertInstanceOf(Location::class, $equipment->currentLocation);
    }

    public function test_equipment_can_be_archived(): void
    {
        $archiver = User::factory()->create();
        $equipment = $this->createEquipment();

        $equipment->update([
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $archiver->id,
        ]);

        $this->assertTrue($equipment->fresh()->is_archived);
        $this->assertTrue($equipment->fresh()->archivedBy->is($archiver));
    }

    public function test_active_scope_excludes_archived_equipment(): void
    {
        $active = $this->createEquipment(['equipment_code' => 'EQ-ACTIVE-001']);
        $archived = $this->createEquipment([
            'equipment_code' => 'EQ-ARCHIVED-001',
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $activeEquipment = Equipment::active()->pluck('id');

        $this->assertTrue($activeEquipment->contains($active->id));
        $this->assertFalse($activeEquipment->contains($archived->id));
    }

    public function test_archived_scope_returns_archived_equipment(): void
    {
        $this->createEquipment(['equipment_code' => 'EQ-ACTIVE-001']);
        $archived = $this->createEquipment([
            'equipment_code' => 'EQ-ARCHIVED-001',
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $archivedEquipment = Equipment::archived()->pluck('id');

        $this->assertTrue($archivedEquipment->contains($archived->id));
    }

    public function test_equipment_location_history_records_from_and_to_locations(): void
    {
        $from = Location::create(['name' => 'From Room', 'type' => 'Room']);
        $to = Location::create(['name' => 'To Room', 'type' => 'Room']);
        $equipment = $this->createEquipment(['current_location_id' => $from->id]);

        $history = EquipmentLocationHistory::create([
            'equipment_id' => $equipment->id,
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'transferred_at' => now(),
            'remarks' => 'Moved for testing.',
        ]);

        $this->assertTrue($history->fromLocation->is($from));
        $this->assertTrue($history->toLocation->is($to));
    }

    public function test_equipment_location_history_belongs_to_equipment(): void
    {
        $to = Location::create(['name' => 'To Room', 'type' => 'Room']);
        $equipment = $this->createEquipment();

        $history = EquipmentLocationHistory::create([
            'equipment_id' => $equipment->id,
            'to_location_id' => $to->id,
            'transferred_at' => now(),
        ]);

        $this->assertTrue($history->equipment->is($equipment));
        $this->assertTrue($equipment->locationHistories->contains($history));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-TEST-001',
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }
}
