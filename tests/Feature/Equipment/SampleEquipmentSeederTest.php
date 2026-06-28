<?php

namespace Tests\Feature\Equipment;

use App\Models\Equipment;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SampleEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleEquipmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_equipment_records_are_created(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        foreach (SampleEquipmentSeeder::SAMPLE_CODES as $code) {
            $this->assertDatabaseHas('equipment', ['equipment_code' => $code]);
        }
    }

    public function test_sample_equipment_seeder_is_idempotent(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->assertSame(count(SampleEquipmentSeeder::SAMPLE_CODES), Equipment::count());
    }

    public function test_sample_equipment_seeder_skips_when_master_data_is_missing(): void
    {
        $this->seed(SampleEquipmentSeeder::class);

        $this->assertSame(0, Equipment::count());
    }
}
