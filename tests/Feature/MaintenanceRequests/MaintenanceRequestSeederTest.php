<?php

namespace Tests\Feature\MaintenanceRequests;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Database\Seeders\MaintenanceRequestSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SampleEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRequestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_requests_are_created_when_sample_equipment_and_user_exist(): void
    {
        User::factory()->create();
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->seed(MaintenanceRequestSeeder::class);

        $this->assertSame(2, MaintenanceRequest::count());
        $this->assertDatabaseHas('maintenance_requests', [
            'request_number' => 'MR-SAMPLE-0001',
            'severity' => 'High',
            'status' => 'Submitted',
        ]);
    }

    public function test_sample_request_seeder_is_idempotent(): void
    {
        User::factory()->create();
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->seed(MaintenanceRequestSeeder::class);
        $this->seed(MaintenanceRequestSeeder::class);

        $this->assertSame(2, MaintenanceRequest::count());
    }

    public function test_sample_request_seeder_skips_without_equipment_or_user(): void
    {
        $this->seed(MaintenanceRequestSeeder::class);

        $this->assertSame(0, MaintenanceRequest::count());
    }
}
