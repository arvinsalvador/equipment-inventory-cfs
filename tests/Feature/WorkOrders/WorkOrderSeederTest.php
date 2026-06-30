<?php

namespace Tests\Feature\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SampleEquipmentSeeder;
use Database\Seeders\WorkOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_work_orders_are_created_when_sample_equipment_and_user_exist(): void
    {
        User::factory()->create();
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->seed(WorkOrderSeeder::class);

        $this->assertSame(2, WorkOrder::count());
        $this->assertDatabaseHas('work_orders', [
            'work_order_number' => 'WO-SAMPLE-0001',
            'title' => 'Inspect weather station signal loss',
            'priority' => 'High',
            'status' => 'Available',
        ]);
    }

    public function test_sample_work_order_seeder_is_idempotent(): void
    {
        User::factory()->create();
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->seed(WorkOrderSeeder::class);
        $this->seed(WorkOrderSeeder::class);

        $this->assertSame(2, WorkOrder::count());
    }

    public function test_sample_work_order_seeder_skips_without_equipment_or_user(): void
    {
        $this->seed(WorkOrderSeeder::class);

        $this->assertSame(0, WorkOrder::count());
    }
}
