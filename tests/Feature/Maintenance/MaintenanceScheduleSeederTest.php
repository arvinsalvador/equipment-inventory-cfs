<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceSchedule;
use Database\Seeders\MaintenanceScheduleSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SampleEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceScheduleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_schedules_are_created_when_sample_equipment_exists(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->seed(MaintenanceScheduleSeeder::class);

        $this->assertSame(2, MaintenanceSchedule::count());
        $this->assertDatabaseHas('maintenance_schedules', [
            'maintenance_type' => 'Sensor inspection',
            'maintenance_frequency' => 'Monthly',
            'priority' => 'High',
        ]);
    }

    public function test_sample_schedule_seeder_is_idempotent(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->seed(MaintenanceScheduleSeeder::class);
        $this->seed(MaintenanceScheduleSeeder::class);

        $this->assertSame(2, MaintenanceSchedule::count());
    }

    public function test_sample_schedule_seeder_skips_when_no_equipment_exists(): void
    {
        $this->seed(MaintenanceScheduleSeeder::class);

        $this->assertSame(0, MaintenanceSchedule::count());
    }
}
