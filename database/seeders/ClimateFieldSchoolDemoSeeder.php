<?php

namespace Database\Seeders;

use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use App\Services\EquipmentLifecycleAnalyzer;
use App\Services\MaintenanceRecommendationEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Role;

class ClimateFieldSchoolDemoSeeder extends Seeder
{
    /**
     * WARNING: This seeder wipes demo/application data and should only be used
     * in local, testing, staging, or fresh deployment environments.
     */
    private const DATA_FILE = 'data/climate-field-school-equipment.csv';

    /** @var array<string, array{name: string, description: string, prefix: string}> */
    private const GOVERNMENT_CATEGORIES = [
        '10604010' => ['name' => 'Building', 'description' => 'Building assets used for Climate Field School operations.', 'prefix' => 'BLD'],
        '10604020' => ['name' => 'School Buildings', 'description' => 'School building assets and improvements.', 'prefix' => 'SCH'],
        '10604990' => ['name' => 'Other Structures', 'description' => 'Other structure assets supporting field school operations.', 'prefix' => 'STR'],
        '10605020' => ['name' => 'Office Equipment', 'description' => 'Presentation, classroom, and administrative equipment.', 'prefix' => 'OFF'],
        '10607010' => ['name' => 'Furniture & Fixtures', 'description' => 'Furniture, fixtures, and classroom fittings.', 'prefix' => 'FNF'],
        '10605030' => ['name' => 'Information and Communication Technology Equipment', 'description' => 'Computing, networking, camera, software, and digital learning equipment.', 'prefix' => 'ICT'],
        '10607020' => ['name' => 'Books', 'description' => 'Books, learning references, and library materials.', 'prefix' => 'BKS'],
        '10605040' => ['name' => 'Agricultural, Fishery & Forestry Equipment', 'description' => 'Agricultural, fishery, forestry, aquaculture, and field operation equipment.', 'prefix' => 'AFF'],
        '10605110' => ['name' => 'Medical, Dental & Laboratory Equipment', 'description' => 'Clinical, laboratory, and diagnostic equipment.', 'prefix' => 'MDL'],
        '10605140' => ['name' => 'Technical & Scientific Equipment', 'description' => 'Technical, scientific, calibration, monitoring, and analytical equipment.', 'prefix' => 'TSE'],
    ];

    /** @var array<string, string> */
    private const SEED_CATEGORY_ACCOUNT_CODES = [
        'Office Equipment' => '10605020',
        'ICT Equipment' => '10605030',
        'Other Equipment' => '10605020',
        'Technical and Scientific Equipment' => '10605140',
        'Marine and Fisheries Equipment' => '10605040',
        'Agricultural and Forestry Equipment' => '10605040',
        'Supplies and Materials' => '10605020',
        'A.I. Equipment for Poultry' => '10605040',
        'A.I. Equipment for Small and Large Ruminants' => '10605040',
        'A.I. Equipment for Swine' => '10605040',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ClimateFieldSchoolDemoSeeder is for local, testing, staging, or fresh demo environments only.');
        }

        $this->call(RolePermissionSeeder::class);
        $this->resetDemoData();

        $users = $this->ensureDemoUsers();
        $location = $this->createClimateFieldSchoolLocation();
        $categories = $this->createCategories();
        $equipment = $this->createEquipment($location, $categories);

        $this->createMaintenanceSchedules($equipment, $users['technician']);
        $requests = $this->createMaintenanceRequests($equipment, $users);
        $workOrders = $this->createWorkOrders($equipment, $requests, $users);
        $this->createEvidence($workOrders, $users['technician']);
        $this->createLifecycleProfilesAndRecommendations($equipment);
        $assetActions = $this->createAssetActionRequests($equipment, $users);
        $this->createBudgetPlans($equipment, $assetActions, $users);
        $this->createNotifications($equipment, $workOrders, $users);
    }

    private function resetDemoData(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'budget_plan_items',
            'budget_plans',
            'asset_action_requests',
            'maintenance_recommendations',
            'equipment_lifecycle_profiles',
            'work_order_evidences',
            'work_orders',
            'maintenance_requests',
            'maintenance_schedules',
            'equipment_location_histories',
            'equipment',
            'equipment_categories',
            'locations',
            'system_notifications',
            'audit_logs',
            'browser_push_subscriptions',
            'user_notification_preferences',
        ] as $table) {
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * @return array{administrator: User, staff: User, technician: User}
     */
    private function ensureDemoUsers(): array
    {
        $password = Hash::make('password');

        $administrator = User::firstOrCreate(
            ['email' => 'admin.cfs.demo@example.test'],
            ['name' => 'Climate Field School Admin', 'password' => $password],
        );
        $staff = User::firstOrCreate(
            ['email' => 'staff.cfs.demo@example.test'],
            ['name' => 'Climate Field School Staff', 'password' => $password],
        );
        $technician = User::firstOrCreate(
            ['email' => 'technician.cfs.demo@example.test'],
            ['name' => 'Climate Field School Technician', 'password' => $password],
        );

        $administrator->assignRole(Role::findByName('Administrator', 'web'));
        $staff->assignRole(Role::findByName('Staff', 'web'));
        $technician->assignRole(Role::findByName('Technician', 'web'));

        return compact('administrator', 'staff', 'technician');
    }

    private function createClimateFieldSchoolLocation(): Location
    {
        return Location::create([
            'name' => 'Climate Field School',
            'type' => 'Building',
            'description' => 'Climate Field School Building / Laboratory and Training Facility. Campus: SNSU Del Carmen Campus, Siargao Island, Surigao del Norte.',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, EquipmentCategory>
     */
    private function createCategories(): array
    {
        $categories = [];

        foreach (self::GOVERNMENT_CATEGORIES as $accountCode => $category) {
            $categories[$accountCode] = EquipmentCategory::updateOrCreate(
                ['account_code' => $accountCode],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                ],
            );
        }

        return $categories;
    }

    /**
     * @param  array<string, EquipmentCategory>  $categories
     * @return Collection<int, Equipment>
     */
    private function createEquipment(Location $location, array $categories)
    {
        $created = collect();
        $sequence = 1;

        foreach ($this->equipmentRows() as $row) {
            $accountCode = $this->accountCode($row);
            $category = $categories[$accountCode];
            $categoryName = $category->name;
            $quantity = max(1, (int) ($row['quantity'] ?: 1));

            for ($copy = 1; $copy <= $quantity; $copy++) {
                $code = sprintf('CFS-%s-%04d', self::GOVERNMENT_CATEGORIES[$accountCode]['prefix'] ?? 'EQP', $sequence);
                $acquiredAt = $this->deterministicDate($row['source_row'].$row['clean_equipment_name']);
                $cost = $this->sampleCost($row['clean_equipment_name'], $categoryName);
                $condition = $this->conditionForSequence($sequence);

                $equipment = Equipment::create([
                    'equipment_code' => $code,
                    'property_number' => sprintf('%s-CFS-%04d', $accountCode, $sequence),
                    'equipment_name' => $row['clean_equipment_name'],
                    'equipment_category_id' => $category->id,
                    'description' => $row['specification'] ?: 'Demo specification pending validation from official property records.',
                    'brand' => $this->brandFor($row['clean_equipment_name'], $row['specification']),
                    'model' => $this->modelFor($row['specification']),
                    'serial_number' => sprintf('CFS-DEMO-%04d', $sequence),
                    'acquisition_date' => $acquiredAt->toDateString(),
                    'acquisition_cost' => $cost,
                    'current_location_id' => $location->id,
                    'custodian' => 'Climate Field School',
                    'condition' => $condition,
                    'operational_status' => $condition === 'Defective' ? 'Under inspection' : ($condition === 'Beyond repair' ? 'Unavailable' : 'Available'),
                    'maintenance_frequency' => $this->maintenanceFrequency($categoryName),
                    'last_maintenance_date' => $sequence % 5 === 0 ? now()->subMonths(3)->toDateString() : null,
                    'next_maintenance_date' => $this->nextMaintenanceDate($sequence),
                    'warranty_expiration_date' => $acquiredAt->addYears($this->warrantyYears($categoryName))->toDateString(),
                    'qr_code_generated_at' => now()->subDays($sequence % 30),
                    'remarks' => $this->equipmentRemarks($row),
                ]);

                $created->push($equipment);
                $sequence++;
            }
        }

        return $created;
    }

    private function createMaintenanceSchedules($equipment, User $technician): void
    {
        $statuses = ['Upcoming', 'Due soon', 'Overdue', 'Completed'];

        $equipment->take(60)->values()->each(function (Equipment $item, int $index) use ($technician, $statuses): void {
            $status = $statuses[$index % count($statuses)];
            $scheduledDate = match ($status) {
                'Due soon' => today()->addDays(3),
                'Overdue' => today()->subDays(12),
                'Completed' => today()->subDays(30),
                default => today()->addDays(20 + ($index % 20)),
            };

            MaintenanceSchedule::create([
                'equipment_id' => $item->id,
                'maintenance_type' => $this->maintenanceType($item),
                'maintenance_frequency' => $item->maintenance_frequency ?: 'Quarterly',
                'scheduled_date' => $scheduledDate->toDateString(),
                'assigned_user_id' => $technician->id,
                'priority' => $index % 9 === 0 ? 'High' : 'Normal',
                'checklist_instructions' => 'Inspect, clean, calibrate where applicable, and record operating condition.',
                'status' => $status,
                'completed_at' => $status === 'Completed' ? now()->subDays(29) : null,
                'completed_by' => $status === 'Completed' ? $technician->id : null,
                'completion_remarks' => $status === 'Completed' ? 'Demo preventive maintenance completed.' : null,
                'remarks' => 'Climate Field School demo preventive maintenance schedule.',
            ]);
        });
    }

    /**
     * @param  array{administrator: User, staff: User, technician: User}  $users
     * @return Collection<int, MaintenanceRequest>
     */
    private function createMaintenanceRequests($equipment, array $users)
    {
        $samples = [
            ['Laptop', 'Laptop battery issue reported during field lecture.', 'Moderate', 'Submitted'],
            ['Printer with Scanner', 'Printer not printing properly and scanner needs cleaning.', 'Moderate', 'For review'],
            ['Multimedia Projector', 'Projector needs cleaning and focus adjustment.', 'Low', 'Approved'],
            ['Automatic Weather Station', 'Weather station calibration needed before module deployment.', 'High', 'Converted'],
            ['Multiparameter Water Quality Meter', 'Water quality meter calibration required.', 'High', 'Converted'],
            ['Ultra Low Temperature Freezer', 'Freezer temperature alert requires immediate inspection.', 'Critical', 'Converted'],
            ['Enterprise RTK Mapping Drone Kit', 'UAV propeller inspection and battery diagnostics needed.', 'High', 'Converted'],
            ['Microscope', 'Microscope lens cleaning and illumination check.', 'Low', 'Rejected'],
        ];

        return collect($samples)->map(function (array $sample, int $index) use ($equipment, $users): MaintenanceRequest {
            $item = $this->findEquipment($equipment, $sample[0]);

            return MaintenanceRequest::create([
                'request_number' => sprintf('MR-CFS-%04d', $index + 1),
                'equipment_id' => $item->id,
                'submitted_by' => $users['staff']->id,
                'problem_description' => $sample[1],
                'severity' => $sample[2],
                'status' => $sample[3],
                'reviewed_by' => in_array($sample[3], ['Approved', 'Converted', 'Rejected'], true) ? $users['administrator']->id : null,
                'reviewed_at' => in_array($sample[3], ['Approved', 'Converted', 'Rejected'], true) ? now()->subDays(12 - $index) : null,
                'rejected_by' => $sample[3] === 'Rejected' ? $users['administrator']->id : null,
                'rejected_at' => $sample[3] === 'Rejected' ? now()->subDays(2) : null,
                'rejection_reason' => $sample[3] === 'Rejected' ? 'Resolved through routine cleaning before conversion.' : null,
                'converted_by' => $sample[3] === 'Converted' ? $users['administrator']->id : null,
                'converted_at' => $sample[3] === 'Converted' ? now()->subDays(7 - min($index, 6)) : null,
                'remarks' => 'Climate Field School demo maintenance request.',
            ]);
        });
    }

    /**
     * @param  array{administrator: User, staff: User, technician: User}  $users
     * @return Collection<int, WorkOrder>
     */
    private function createWorkOrders($equipment, $requests, array $users)
    {
        $samples = [
            ['Automatic Weather Station', 3, 'Calibrate automatic weather station', 'High', 'Available'],
            ['Multiparameter Water Quality Meter', 4, 'Calibrate water quality meter', 'High', 'Assigned'],
            ['Ultra Low Temperature Freezer', 5, 'Investigate freezer temperature alert', 'Critical', 'In progress'],
            ['Enterprise RTK Mapping Drone Kit', 6, 'Inspect UAV propellers and batteries', 'High', 'For verification'],
            ['Printer with Scanner', null, 'Repair printer feed and clean scanner bed', 'Normal', 'Completed'],
            ['Laptop', null, 'Replace laptop battery pack', 'Normal', 'Completed'],
            ['Projector', null, 'Clean projector optics', 'Low', 'Completed'],
            ['PCR Thermocycler', null, 'PCR thermocycler control board failure assessment', 'Critical', 'Beyond repair'],
            ['Water Baths and Magnetic Stirrers', null, 'Completed work pending after-maintenance evidence', 'Normal', 'Completed'],
        ];

        $orders = collect();

        foreach ($samples as $index => $sample) {
            $item = $this->findEquipment($equipment, $sample[0]);
            $status = $sample[4];

            $orders->push(WorkOrder::create([
                'work_order_number' => sprintf('WO-CFS-%04d', $index + 1),
                'maintenance_request_id' => $sample[1] !== null ? $requests[$sample[1]]?->id : null,
                'equipment_id' => $item->id,
                'created_by' => $users['administrator']->id,
                'assigned_to' => in_array($status, ['Assigned', 'In progress', 'For verification', 'Completed', 'Beyond repair'], true) ? $users['technician']->id : null,
                'accepted_by' => in_array($status, ['In progress', 'For verification', 'Completed', 'Beyond repair'], true) ? $users['technician']->id : null,
                'verified_by' => $status === 'Completed' ? $users['administrator']->id : null,
                'title' => $sample[2],
                'problem_description' => $sample[2].' for Climate Field School demo operations.',
                'priority' => $sample[3],
                'status' => $status,
                'findings' => $status === 'Beyond repair' ? 'Controller board failure with unstable heating cycle.' : 'Demo diagnostic findings recorded.',
                'action_performed' => in_array($status, ['Completed', 'For verification'], true) ? 'Inspection, cleaning, calibration, and functional test completed.' : null,
                'completion_remarks' => $status === 'Completed' ? 'Demo work order completed.' : null,
                'final_equipment_condition' => $status === 'Beyond repair' ? 'Beyond repair' : (in_array($status, ['Completed', 'For verification'], true) ? 'Good' : null),
                'final_operational_status' => $status === 'Beyond repair' ? 'Unavailable' : (in_array($status, ['Completed', 'For verification'], true) ? 'Available' : null),
                'beyond_repair_reason' => $status === 'Beyond repair' ? 'Repair cost exceeds practical demo replacement threshold.' : null,
                'recommended_action' => $status === 'Beyond repair' ? 'Replace equipment and document disposal assessment.' : null,
                'available_at' => now()->subDays(12),
                'assigned_at' => in_array($status, ['Assigned', 'In progress', 'For verification', 'Completed', 'Beyond repair'], true) ? now()->subDays(10) : null,
                'accepted_at' => in_array($status, ['In progress', 'For verification', 'Completed', 'Beyond repair'], true) ? now()->subDays(9) : null,
                'started_at' => in_array($status, ['In progress', 'For verification', 'Completed', 'Beyond repair'], true) ? now()->subDays(8) : null,
                'completed_at' => in_array($status, ['Completed', 'Beyond repair'], true) ? now()->subDays(4) : null,
                'verified_at' => $status === 'Completed' ? now()->subDays(3) : null,
                'due_date' => today()->addDays(5 - $index)->toDateString(),
                'labor_cost' => 500 + ($index * 120),
                'parts_cost' => $status === 'Beyond repair' ? 0 : 800 + ($index * 250),
                'external_service_cost' => $sample[3] === 'Critical' ? 2500 : 0,
                'remarks' => 'Climate Field School demo work order.',
            ]));
        }

        $repeatedRepairEquipment = $this->findEquipment($equipment, 'Laptop');
        for ($repair = 1; $repair <= 3; $repair++) {
            $orders->push(WorkOrder::create([
                'work_order_number' => sprintf('WO-CFS-RPT-%04d', $repair),
                'equipment_id' => $repeatedRepairEquipment->id,
                'created_by' => $users['administrator']->id,
                'assigned_to' => $users['technician']->id,
                'accepted_by' => $users['technician']->id,
                'verified_by' => $users['administrator']->id,
                'title' => "Repeated laptop repair {$repair}",
                'problem_description' => 'Recurring laptop reliability issue for recommendation demo.',
                'priority' => 'Normal',
                'status' => 'Completed',
                'findings' => 'Recurring heat and battery symptoms observed.',
                'action_performed' => 'Cleaned vents and tested charging cycle.',
                'completion_remarks' => 'Repeated repair demo record.',
                'final_equipment_condition' => 'Good',
                'final_operational_status' => 'Available',
                'available_at' => now()->subDays(50 - $repair),
                'assigned_at' => now()->subDays(49 - $repair),
                'accepted_at' => now()->subDays(48 - $repair),
                'started_at' => now()->subDays(47 - $repair),
                'completed_at' => now()->subDays(46 - $repair),
                'verified_at' => now()->subDays(45 - $repair),
                'due_date' => today()->subDays(45 - $repair)->toDateString(),
                'labor_cost' => 750,
                'parts_cost' => 350,
                'external_service_cost' => 0,
                'remarks' => 'Repeated repair condition for recommendation demo.',
            ]));
        }

        $defective = $equipment->firstWhere('equipment_name', 'Nitrogen Tank') ?: $equipment->skip(20)->first();
        $defective->forceFill([
            'condition' => 'Defective',
            'operational_status' => 'Under inspection',
        ])->save();

        return $orders;
    }

    private function createEvidence($workOrders, User $technician): void
    {
        $workOrders->filter(fn (WorkOrder $order): bool => in_array($order->status, ['Completed', 'For verification'], true))
            ->reject(fn (WorkOrder $order): bool => str_contains($order->title, 'pending after-maintenance evidence'))
            ->each(function (WorkOrder $order, int $index) use ($technician): void {
                WorkOrderEvidence::create([
                    'work_order_id' => $order->id,
                    'equipment_id' => $order->equipment_id,
                    'evidence_type' => 'Before maintenance',
                    'image_path' => sprintf('demo/cfs/work-orders/%s-before.jpg', $order->work_order_number),
                    'caption' => 'Demo before-maintenance evidence metadata.',
                    'uploaded_by' => $technician->id,
                    'uploaded_at' => now()->subDays(5)->addMinutes($index),
                ]);

                WorkOrderEvidence::create([
                    'work_order_id' => $order->id,
                    'equipment_id' => $order->equipment_id,
                    'evidence_type' => 'After maintenance',
                    'image_path' => sprintf('demo/cfs/work-orders/%s-after.jpg', $order->work_order_number),
                    'caption' => 'Demo after-maintenance evidence metadata.',
                    'uploaded_by' => $technician->id,
                    'uploaded_at' => now()->subDays(4)->addMinutes($index),
                ]);
            });

        $beyondRepair = $workOrders->firstWhere('status', 'Beyond repair');
        if ($beyondRepair) {
            WorkOrderEvidence::create([
                'work_order_id' => $beyondRepair->id,
                'equipment_id' => $beyondRepair->equipment_id,
                'evidence_type' => 'Beyond-repair evidence',
                'image_path' => sprintf('demo/cfs/work-orders/%s-beyond-repair-1.jpg', $beyondRepair->work_order_number),
                'caption' => 'Only one beyond-repair evidence item is intentionally seeded for recommendation demo.',
                'uploaded_by' => $technician->id,
                'uploaded_at' => now()->subDays(3),
            ]);
        }
    }

    private function createLifecycleProfilesAndRecommendations($equipment): void
    {
        $analyzer = app(EquipmentLifecycleAnalyzer::class);

        $equipment->each(fn (Equipment $item): EquipmentLifecycleProfile => $analyzer->analyze($item->refresh()));

        app(MaintenanceRecommendationEngine::class)->generateForAllEquipment();

        MaintenanceRecommendation::query()->orderBy('id')->take(12)->get()->each(function (MaintenanceRecommendation $recommendation, int $index): void {
            $status = ['Pending', 'Approved', 'Rejected', 'Executed'][$index % 4];
            $recommendation->forceFill([
                'action_status' => $status,
                'actioned_at' => $status === 'Pending' ? null : now()->subDays($index + 1),
                'action_notes' => $status === 'Pending' ? null : "Demo recommendation action marked {$status}.",
            ])->save();
        });
    }

    /**
     * @param  array{administrator: User, staff: User, technician: User}  $users
     * @return Collection<int, AssetActionRequest>
     */
    private function createAssetActionRequests($equipment, array $users)
    {
        $samples = [
            ['PCR Thermocycler', 'Replacement', 'Replace failed PCR thermocycler.', 'Critical', 'Approved'],
            ['Printer with Scanner', 'Major Repair', 'Repair recurring printer feed issue.', 'Normal', 'Submitted'],
            ['Laptop', 'Replacement', 'Replace repeated-repair laptop unit.', 'High', 'Under Review'],
            ['Water Quality Sensors with Datalogger', 'Inspection', 'Inspect calibration reliability before field deployment.', 'High', 'Rejected'],
            ['PCR Thermocycler', 'Disposal', 'Dispose beyond-repair laboratory equipment after replacement approval.', 'Critical', 'Completed'],
        ];

        return collect($samples)->map(function (array $sample, int $index) use ($equipment, $users): AssetActionRequest {
            $status = $sample[4];

            return AssetActionRequest::create([
                'request_number' => sprintf('AAR-CFS-%04d', $index + 1),
                'equipment_id' => $this->findEquipment($equipment, $sample[0])->id,
                'requested_by' => $users['staff']->id,
                'reviewed_by' => in_array($status, ['Under Review', 'Approved', 'Rejected', 'Completed'], true) ? $users['administrator']->id : null,
                'approved_by' => in_array($status, ['Approved', 'Completed'], true) ? $users['administrator']->id : null,
                'completed_by' => $status === 'Completed' ? $users['administrator']->id : null,
                'request_type' => $sample[1],
                'reason' => $sample[2],
                'justification' => 'Climate Field School demo asset action workflow.',
                'recommended_action' => $sample[1],
                'estimated_cost' => $sample[3] === 'Critical' ? 185000 : 45000,
                'priority' => $sample[3],
                'status' => $status,
                'reviewed_at' => in_array($status, ['Under Review', 'Approved', 'Rejected', 'Completed'], true) ? now()->subDays(6) : null,
                'approved_at' => in_array($status, ['Approved', 'Completed'], true) ? now()->subDays(5) : null,
                'rejected_at' => $status === 'Rejected' ? now()->subDays(4) : null,
                'completed_at' => $status === 'Completed' ? now()->subDays(2) : null,
                'rejection_reason' => $status === 'Rejected' ? 'Calibration can be handled through preventive maintenance first.' : null,
                'remarks' => 'Demo asset action request.',
                'metadata' => ['source' => 'ClimateFieldSchoolDemoSeeder'],
            ]);
        });
    }

    /**
     * @param  array{administrator: User, staff: User, technician: User}  $users
     */
    private function createBudgetPlans($equipment, $assetActions, array $users): void
    {
        $plans = [
            ['Annual Maintenance Budget Plan', 'Prepared', 'Annual preventive and corrective maintenance for Climate Field School equipment.'],
            ['Equipment Replacement Forecast', 'Approved', 'Replacement planning for critical and beyond-repair demo assets.'],
            ['Laboratory Equipment Upgrade Plan', 'Under Review', 'Upgrade plan for laboratory and scientific equipment.'],
        ];

        foreach ($plans as $planIndex => $planData) {
            $plan = BudgetPlan::create([
                'plan_number' => sprintf('BP-CFS-2026-%04d', $planIndex + 1),
                'title' => $planData[0],
                'fiscal_year' => 2026,
                'budget_date' => now()->subMonths($planIndex)->toDateString(),
                'funds' => ['GAA', 'IGF', 'Trust Fund'][$planIndex % 3],
                'purchase_order_number' => $planIndex === 1 ? 'PO-CFS-2026-0002' : null,
                'description' => $planData[2],
                'status' => $planData[1],
                'prepared_by' => $users['staff']->id,
                'reviewed_by' => in_array($planData[1], ['Under Review', 'Approved'], true) ? $users['administrator']->id : null,
                'approved_by' => $planData[1] === 'Approved' ? $users['administrator']->id : null,
                'reviewed_at' => in_array($planData[1], ['Under Review', 'Approved'], true) ? now()->subDays(3) : null,
                'approved_at' => $planData[1] === 'Approved' ? now()->subDays(2) : null,
                'remarks' => 'Climate Field School demo budget plan.',
                'metadata' => ['source' => 'ClimateFieldSchoolDemoSeeder'],
            ]);

            $items = $equipment->slice($planIndex * 5, 5)->values();
            $items->each(function (Equipment $item, int $itemIndex) use ($plan, $assetActions): void {
                BudgetPlanItem::create([
                    'budget_plan_id' => $plan->id,
                    'equipment_id' => $item->id,
                    'asset_action_request_id' => $itemIndex === 0 ? $assetActions->get(0)?->id : null,
                    'item_type' => ['Replacement', 'Major Repair', 'Inspection', 'Procurement', 'Disposal Support'][$itemIndex % 5],
                    'description' => "Budget item for {$item->equipment_name}.",
                    'priority' => ['Critical', 'High', 'Normal', 'Normal', 'Low'][$itemIndex % 5],
                    'estimated_cost' => max(5000, (float) $item->acquisition_cost * ($itemIndex === 0 ? 1.10 : 0.18)),
                    'justification' => 'Demo budget planning item derived from Climate Field School equipment.',
                    'forecast_reason' => 'Seeded to support budget planning and replacement forecast reports.',
                    'target_period' => 'FY 2026',
                    'status' => ['Approved', 'Proposed', 'Deferred', 'Rejected', 'Completed'][$itemIndex % 5],
                    'metadata' => ['source' => 'ClimateFieldSchoolDemoSeeder'],
                ]);
            });

            $plan->recalculateTotal();
        }
    }

    /**
     * @param  array{administrator: User, staff: User, technician: User}  $users
     */
    private function createNotifications($equipment, $workOrders, array $users): void
    {
        $samples = [
            ['Overdue maintenance alert', 'Preventive maintenance is overdue for selected Climate Field School equipment.', 'Warning', 'High', 'Preventive Maintenance', $equipment->first()],
            ['Assigned work order', 'A Climate Field School work order has been assigned to the technician.', 'Information', 'Normal', 'Work Order', $workOrders->firstWhere('status', 'Assigned')],
            ['Pending verification', 'A completed field school work order is awaiting verification.', 'Reminder', 'High', 'Work Order', $workOrders->firstWhere('status', 'For verification')],
            ['Budget approval required', 'A Climate Field School budget plan is ready for review.', 'Reminder', 'Normal', 'System', null],
            ['Replacement candidate', 'A critical asset has been marked for replacement planning.', 'Critical', 'Critical', 'Equipment Lifecycle', $equipment->firstWhere('condition', 'Beyond repair')],
        ];

        foreach ($samples as $sample) {
            $related = $sample[5];

            SystemNotification::create([
                'user_id' => $users['administrator']->id,
                'title' => $sample[0],
                'message' => $sample[1],
                'notification_type' => $sample[2],
                'priority' => $sample[3],
                'category' => $sample[4],
                'related_type' => $related ? $related::class : null,
                'related_id' => $related?->id,
                'action_url' => '/admin',
                'generated_at' => now()->subHours(6),
                'expires_at' => now()->addDays(30),
                'metadata' => ['source' => 'ClimateFieldSchoolDemoSeeder'],
            ]);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function equipmentRows(): array
    {
        $path = database_path('seeders/'.self::DATA_FILE);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to read Climate Field School equipment dataset at {$path}.");
        }

        $header = fgetcsv($handle);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function categoryName(array $row): string
    {
        return self::GOVERNMENT_CATEGORIES[$this->accountCode($row)]['name'];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function accountCode(array $row): string
    {
        $name = strtolower($row['clean_equipment_name']);
        $seedCategory = $row['seed_category'];

        return match (true) {
            str_contains($name, 'book'), str_contains($name, 'manual') => '10607020',
            str_contains($name, 'chair'), str_contains($name, 'table'), str_contains($name, 'cabinet'), str_contains($name, 'shelf') => '10607010',
            str_contains($name, 'clinic'), str_contains($name, 'medical'), str_contains($name, 'dental') => '10605110',
            str_contains($name, 'laptop'), str_contains($name, 'computer'), str_contains($name, 'printer'), str_contains($name, 'camera'), str_contains($name, 'software'), str_contains($name, 'server'), str_contains($name, 'projector'), str_contains($name, 'gps') => '10605030',
            str_contains($name, 'building') => '10604020',
            default => self::SEED_CATEGORY_ACCOUNT_CODES[$seedCategory] ?? '10605020',
        };
    }

    private function deterministicDate(string $seed): CarbonImmutable
    {
        $start = CarbonImmutable::parse('2024-05-01');
        $days = $start->diffInDays(CarbonImmutable::parse('2026-05-31'));

        return $start->addDays(abs(crc32($seed)) % ($days + 1));
    }

    private function sampleCost(string $name, string $category): float
    {
        $text = strtolower($name.' '.$category);

        return match (true) {
            str_contains($text, 'spectrophotometer'),
            str_contains($text, 'sequencer'),
            str_contains($text, 'freezer'),
            str_contains($text, 'uav'),
            str_contains($text, 'drone'),
            str_contains($text, 'server'),
            str_contains($text, 'knowledge portal'),
            str_contains($text, 'tractor') => 250000.00,
            str_contains($text, 'laptop'),
            str_contains($text, 'desktop'),
            str_contains($text, 'weather station'),
            str_contains($text, 'pcr'),
            str_contains($text, 'biosafety'),
            str_contains($text, 'centrifuge'),
            str_contains($text, 'microscope'),
            str_contains($text, 'water quality') => 85000.00,
            str_contains($text, 'printer'),
            str_contains($text, 'projector'),
            str_contains($text, 'smart tv'),
            str_contains($text, 'sound'),
            str_contains($text, 'gps') => 45000.00,
            $category === 'Books' => 3500.00,
            $category === 'Agricultural, Fishery & Forestry Equipment' => 25000.00,
            default => 15000.00,
        };
    }

    private function conditionForSequence(int $sequence): string
    {
        return match (true) {
            $sequence % 41 === 0 => 'Beyond repair',
            $sequence % 29 === 0 => 'Defective',
            $sequence % 11 === 0 => 'Needs maintenance',
            $sequence % 7 === 0 => 'Fair',
            $sequence % 5 === 0 => 'Good',
            default => 'New',
        };
    }

    private function maintenanceFrequency(string $category): string
    {
        return match ($category) {
            'Information and Communication Technology Equipment', 'Office Equipment' => 'Monthly',
            'Technical & Scientific Equipment', 'Medical, Dental & Laboratory Equipment' => 'Quarterly',
            'Agricultural, Fishery & Forestry Equipment' => 'Semi-annually',
            'Books' => 'Annually',
            default => 'Annually',
        };
    }

    private function nextMaintenanceDate(int $sequence): string
    {
        return match (true) {
            $sequence % 13 === 0 => today()->subDays(10)->toDateString(),
            $sequence % 17 === 0 => today()->addDays(5)->toDateString(),
            default => today()->addDays(30 + ($sequence % 90))->toDateString(),
        };
    }

    private function warrantyYears(string $category): int
    {
        return in_array($category, ['Information and Communication Technology Equipment', 'Technical & Scientific Equipment', 'Medical, Dental & Laboratory Equipment'], true) ? 3 : 1;
    }

    private function brandFor(string $name, string $specification): ?string
    {
        $text = $name.' '.$specification;

        foreach (['Apple', 'Epson', 'Canon', 'Garmin', 'Dell', 'Lenovo', 'Hanna', 'Davis', 'Autel', 'Keysight', 'Acer'] as $brand) {
            if (stripos($text, $brand) !== false) {
                return $brand;
            }
        }

        return null;
    }

    private function modelFor(string $specification): ?string
    {
        if (preg_match('/Model:\s*([^\n]+)/i', $specification, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function equipmentRemarks(array $row): string
    {
        return trim(implode("\n", array_filter([
            "Original equipment needed: {$row['equipment_needed']}",
            "Unit: {$row['unit']}",
            "Module: {$row['module']}",
            "Source Excel row: {$row['source_row']}",
            $row['remarks'] ? "Source remarks: {$row['remarks']}" : null,
            'Funding/source: Climate Field School Project / PSF',
            'Supplier/vendor: '.$this->supplierFor($row['clean_equipment_name'], $this->categoryName($row)),
        ])));
    }

    private function supplierFor(string $name, string $category): string
    {
        return match (true) {
            $category === 'Information and Communication Technology Equipment' => 'ICT Equipment Supplier',
            $category === 'Technical & Scientific Equipment' => 'Scientific Equipment Supplier',
            str_contains(strtolower($name), 'laboratory') => 'Laboratory Equipment Supplier',
            default => 'Demo Supplier A',
        };
    }

    private function maintenanceType(Equipment $equipment): string
    {
        return match ($equipment->category?->name) {
            'Information and Communication Technology Equipment', 'Office Equipment' => 'Routine cleaning and functionality check',
            'Technical & Scientific Equipment', 'Medical, Dental & Laboratory Equipment' => 'Calibration and inspection',
            'Agricultural, Fishery & Forestry Equipment' => 'Field readiness inspection',
            default => 'Preventive maintenance',
        };
    }

    private function findEquipment($equipment, string $needle): Equipment
    {
        return $equipment->first(fn (Equipment $item): bool => str_contains(strtolower($item->equipment_name), strtolower($needle)))
            ?? $equipment->firstOrFail();
    }
}
