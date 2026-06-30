<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->oldest('id')->first();

        if (! $creator) {
            return;
        }

        $samples = [
            [
                'work_order_number' => 'WO-SAMPLE-0001',
                'equipment_code' => 'EQ-CFS-0001',
                'title' => 'Inspect weather station signal loss',
                'problem_description' => 'Weather station display intermittently loses signal.',
                'priority' => 'High',
                'status' => 'Available',
            ],
            [
                'work_order_number' => 'WO-SAMPLE-0002',
                'equipment_code' => 'EQ-CFS-0002',
                'title' => 'Check training laptop battery',
                'problem_description' => 'Training laptop battery drains faster than expected.',
                'priority' => 'Normal',
                'status' => 'Assigned',
            ],
        ];

        foreach ($samples as $sample) {
            $equipment = Equipment::where('equipment_code', $sample['equipment_code'])->first();

            if (! $equipment) {
                continue;
            }

            WorkOrder::updateOrCreate(
                ['work_order_number' => $sample['work_order_number']],
                [
                    'equipment_id' => $equipment->id,
                    'created_by' => $creator->id,
                    'title' => $sample['title'],
                    'problem_description' => $sample['problem_description'],
                    'priority' => $sample['priority'],
                    'status' => $sample['status'],
                    'available_at' => $sample['status'] === 'Available' ? now() : null,
                    'remarks' => 'Sample work order.',
                ]
            );
        }
    }
}
