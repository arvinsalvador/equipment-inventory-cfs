<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class MaintenanceRequestSeeder extends Seeder
{
    public function run(): void
    {
        $submitter = User::query()->oldest('id')->first();

        if (! $submitter) {
            return;
        }

        $samples = [
            [
                'request_number' => 'MR-SAMPLE-0001',
                'equipment_code' => 'EQ-CFS-0001',
                'problem_description' => 'Weather station display intermittently loses signal.',
                'severity' => 'High',
                'status' => 'Submitted',
            ],
            [
                'request_number' => 'MR-SAMPLE-0002',
                'equipment_code' => 'EQ-CFS-0002',
                'problem_description' => 'Training laptop battery drains faster than expected.',
                'severity' => 'Moderate',
                'status' => 'For review',
            ],
        ];

        foreach ($samples as $sample) {
            $equipment = Equipment::where('equipment_code', $sample['equipment_code'])->first();

            if (! $equipment) {
                continue;
            }

            MaintenanceRequest::updateOrCreate(
                ['request_number' => $sample['request_number']],
                [
                    'equipment_id' => $equipment->id,
                    'submitted_by' => $submitter->id,
                    'problem_description' => $sample['problem_description'],
                    'severity' => $sample['severity'],
                    'status' => $sample['status'],
                    'remarks' => 'Sample maintenance request.',
                ]
            );
        }
    }
}
