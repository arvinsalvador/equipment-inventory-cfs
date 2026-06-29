<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\MaintenanceSchedule;
use Illuminate\Database\Seeder;

class MaintenanceScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            'EQ-CFS-0001' => [
                'maintenance_type' => 'Sensor inspection',
                'maintenance_frequency' => 'Monthly',
                'priority' => 'High',
                'checklist_instructions' => 'Inspect sensor connections and clean external housing.',
            ],
            'EQ-CFS-0002' => [
                'maintenance_type' => 'System health check',
                'maintenance_frequency' => 'Quarterly',
                'priority' => 'Normal',
                'checklist_instructions' => 'Check updates, battery health, and storage condition.',
            ],
        ];

        foreach ($samples as $equipmentCode => $data) {
            $equipment = Equipment::where('equipment_code', $equipmentCode)->first();

            if (! $equipment) {
                continue;
            }

            MaintenanceSchedule::updateOrCreate(
                [
                    'equipment_id' => $equipment->id,
                    'maintenance_type' => $data['maintenance_type'],
                ],
                array_merge($data, [
                    'scheduled_date' => today()->addMonth(),
                    'status' => 'Upcoming',
                    'remarks' => 'Sample preventive maintenance schedule.',
                ])
            );
        }
    }
}
