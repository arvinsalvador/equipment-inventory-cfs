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
use App\Services\EquipmentQrCodeGenerator;
use App\Services\MaintenanceRecommendationEngine;
use App\Support\OfficialEquipmentInventoryDocumentParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Role;

class OfficialEquipmentInventorySeeder extends Seeder
{
    private const DOCUMENT_FILE = 'data/official-equipment-inventory.docx';

    private array $importSummary = [];

    private const PREFIXES = [
        '10604010' => 'BLD', '10604020' => 'SCH', '10604990' => 'STR', '10605020' => 'OFF',
        '10607010' => 'FNF', '10605030' => 'ICT', '10607020' => 'BKS', '10605040' => 'AFF',
        '10605110' => 'MDL', '10605140' => 'TSE',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('OfficialEquipmentInventorySeeder is destructive and is for local, testing, staging, or fresh demo environments only.');
        }

        $this->call(RolePermissionSeeder::class);
        $this->resetDemoData();

        $users = $this->ensureDemoUsers();
        $defaultLocation = $this->createDefaultLocation();
        $categories = $this->createCategories();
        $equipment = $this->createEquipment($defaultLocation, $categories);

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
            'budget_plan_items', 'budget_plans', 'asset_action_requests', 'maintenance_recommendations',
            'equipment_lifecycle_profiles', 'work_order_evidences', 'work_orders', 'maintenance_requests',
            'maintenance_schedules', 'equipment_location_histories', 'equipment', 'equipment_categories',
            'locations', 'system_notifications', 'audit_logs',
        ] as $table) {
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();
    }

    private function ensureDemoUsers(): array
    {
        $password = Hash::make('password');
        $administrator = User::firstOrCreate(['email' => 'admin.cfs.demo@example.test'], ['name' => 'Official Inventory Admin', 'password' => $password]);
        $staff = User::firstOrCreate(['email' => 'staff.cfs.demo@example.test'], ['name' => 'Official Inventory Staff', 'password' => $password]);
        $technician = User::firstOrCreate(['email' => 'technician.cfs.demo@example.test'], ['name' => 'Official Inventory Technician', 'password' => $password]);

        $administrator->assignRole(Role::findByName('Administrator', 'web'));
        $staff->assignRole(Role::findByName('Staff', 'web'));
        $technician->assignRole(Role::findByName('Technician', 'web'));

        return compact('administrator', 'staff', 'technician');
    }

    private function createDefaultLocation(): Location
    {
        return Location::firstOrCreate(
            ['name' => 'SNSU Del Carmen Campus'],
            ['type' => 'Campus', 'description' => 'SNSU Del Carmen Campus default location for official inventory rows without a specific room or office remark.', 'is_active' => true],
        );
    }

    private function createCategories(): array
    {
        $categories = [];

        foreach (OfficialEquipmentInventoryDocumentParser::APPROVED_CATEGORIES as $accountCode => $name) {
            $existing = EquipmentCategory::query()
                ->where('account_code', $accountCode)
                ->orWhere('name', $name)
                ->orWhere('name', $accountCode.' - '.$name)
                ->first();

            $category = $existing ?: new EquipmentCategory;
            $category->forceFill([
                'account_code' => $accountCode,
                'name' => $name,
                'description' => 'Approved government account-code category from the official inventory document.',
                'is_active' => true,
            ])->save();

            $categories[$accountCode] = $category;
        }

        EquipmentCategory::query()
            ->whereNotIn('account_code', array_keys(OfficialEquipmentInventoryDocumentParser::APPROVED_CATEGORIES))
            ->delete();

        return $categories;
    }

    private function createEquipment(Location $defaultLocation, array $categories)
    {
        $parsed = app(OfficialEquipmentInventoryDocumentParser::class)->parse(database_path('seeders/'.self::DOCUMENT_FILE));
        $this->importSummary = $parsed['summary'];
        $created = collect();
        $qrGenerator = app(EquipmentQrCodeGenerator::class);

        foreach ($parsed['rows'] as $index => $row) {
            $accountCode = $row['account_code'];
            $category = $categories[$accountCode];
            $condition = $this->conditionForSequence($index + 1);
            $location = $this->locationFor($row, $defaultLocation);

            $equipment = Equipment::create([
                'equipment_code' => sprintf('OFF-%s-%04d', self::PREFIXES[$accountCode] ?? 'EQP', $index + 1),
                'property_number' => $row['property_number'] ?: null,
                'equipment_name' => $row['equipment_name'],
                'equipment_category_id' => $category->id,
                'description' => $row['description'] ?: $row['article'],
                'brand' => $this->brandFor($row['equipment_name'], $row['description'].' '.$row['article']),
                'model' => $this->modelFor($row['description'].' '.$row['article']),
                'serial_number' => $this->serialNumberFor($row['description'].' '.$row['article']),
                'acquisition_date' => $row['acquisition_date'],
                'acquisition_cost' => $row['acquisition_cost'],
                'current_location_id' => $location->id,
                'custodian' => $row['remarks'] ?: 'Supply Office',
                'condition' => $condition,
                'operational_status' => $condition === 'Defective' ? 'Under inspection' : ($condition === 'Beyond repair' ? 'Unavailable' : 'Available'),
                'maintenance_frequency' => $this->maintenanceFrequency($category->name),
                'last_maintenance_date' => ($index + 1) % 5 === 0 ? now()->subMonths(3)->toDateString() : null,
                'next_maintenance_date' => $this->nextMaintenanceDate($index + 1),
                'warranty_expiration_date' => null,
                'remarks' => $this->equipmentRemarks($row),
            ]);

            $qrGenerator->generate($equipment);
            $created->push($equipment->refresh());
        }

        $this->outputImportSummary($created);

        return $created;
    }

    private function createMaintenanceSchedules($equipment, User $technician): void
    {
        $statuses = ['Upcoming', 'Due soon', 'Overdue', 'Completed'];

        $equipment->filter(fn (Equipment $item): bool => $item->category?->name !== 'Books')
            ->take(30)
            ->values()
            ->each(function (Equipment $item, int $index) use ($technician, $statuses): void {
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
                    'completion_remarks' => $status === 'Completed' ? 'Official inventory demo preventive maintenance completed.' : null,
                    'remarks' => 'Official inventory demo preventive maintenance schedule.',
                ]);
            });
    }

    private function createMaintenanceRequests($equipment, array $users)
    {
        $samples = [
            ['Acer Nitro 5 Laptop', 'Laptop battery and thermal issue reported.', 'Moderate', 'Submitted'],
            ['Kyocera Copier Machine', 'Copier output quality and feed rollers need service.', 'Moderate', 'For review'],
            ['Koppel Floor-Mounted Air Conditioner', 'Air conditioner requires cleaning and refrigerant inspection.', 'Low', 'Approved'],
            ['Pole Type Distribution Transformer', 'Transformer inspection required after voltage fluctuation report.', 'High', 'Converted'],
            ['Fish Breeding Tank', 'Tank pump and fittings need inspection.', 'High', 'Converted'],
            ['Biological Safety Cabinet', 'Cabinet airflow and filter condition require immediate inspection.', 'Critical', 'Converted'],
            ['4 Wheel Tractor', 'Tractor preventive inspection and fluid check required.', 'High', 'Converted'],
            ['Dental Chair', 'Dental chair requires function and safety inspection.', 'Low', 'Rejected'],
        ];

        return collect($samples)->map(function (array $sample, int $index) use ($equipment, $users): MaintenanceRequest {
            $status = $sample[3];

            return MaintenanceRequest::create([
                'request_number' => sprintf('MR-OFF-%04d', $index + 1),
                'equipment_id' => $this->findEquipment($equipment, $sample[0])->id,
                'submitted_by' => $users['staff']->id,
                'problem_description' => $sample[1],
                'severity' => $sample[2],
                'status' => $status,
                'reviewed_by' => in_array($status, ['Approved', 'Converted', 'Rejected'], true) ? $users['administrator']->id : null,
                'reviewed_at' => in_array($status, ['Approved', 'Converted', 'Rejected'], true) ? now()->subDays(12 - $index) : null,
                'rejected_by' => $status === 'Rejected' ? $users['administrator']->id : null,
                'rejected_at' => $status === 'Rejected' ? now()->subDays(2) : null,
                'rejection_reason' => $status === 'Rejected' ? 'Resolved through routine inspection before conversion.' : null,
                'converted_by' => $status === 'Converted' ? $users['administrator']->id : null,
                'converted_at' => $status === 'Converted' ? now()->subDays(7 - min($index, 6)) : null,
                'remarks' => 'Official inventory demo maintenance request.',
            ]);
        });
    }

    private function createWorkOrders($equipment, $requests, array $users)
    {
        $samples = [
            ['Pole Type Distribution Transformer', 3, 'Inspect transformer and electrical connections', 'High', 'Available'],
            ['Fish Breeding Tank', 4, 'Inspect fish breeding tank system', 'High', 'Assigned'],
            ['Biological Safety Cabinet', 5, 'Inspect biological safety cabinet airflow', 'Critical', 'In progress'],
            ['4 Wheel Tractor', 6, 'Perform tractor readiness inspection', 'High', 'For verification'],
            ['Kyocera Copier Machine', null, 'Repair copier feed and clean scan bed', 'Normal', 'Completed'],
            ['Acer Nitro 5 Laptop', null, 'Diagnose laptop battery and thermal issue', 'Normal', 'Completed'],
            ['Samsung 85-inch Smart TV', null, 'Clean and test smart TV display', 'Low', 'Completed'],
            ['Biological Safety Cabinet', null, 'Assess cabinet beyond-repair condition', 'Critical', 'Beyond repair'],
            ['Forced Air Drying Oven', null, 'Completed work pending after-maintenance evidence', 'Normal', 'Completed'],
        ];

        $orders = collect();
        foreach ($samples as $index => $sample) {
            $status = $sample[4];
            $orders->push(WorkOrder::create([
                'work_order_number' => sprintf('WO-OFF-%04d', $index + 1),
                'maintenance_request_id' => $sample[1] !== null ? $requests[$sample[1]]?->id : null,
                'equipment_id' => $this->findEquipment($equipment, $sample[0])->id,
                'created_by' => $users['administrator']->id,
                'assigned_to' => in_array($status, ['Assigned', 'In progress', 'For verification', 'Completed', 'Beyond repair'], true) ? $users['technician']->id : null,
                'accepted_by' => in_array($status, ['In progress', 'For verification', 'Completed', 'Beyond repair'], true) ? $users['technician']->id : null,
                'verified_by' => $status === 'Completed' ? $users['administrator']->id : null,
                'title' => $sample[2],
                'problem_description' => $sample[2].' for official inventory demo operations.',
                'priority' => $sample[3],
                'status' => $status,
                'findings' => $status === 'Beyond repair' ? 'Safety and functional reliability failed inspection.' : 'Demo diagnostic findings recorded.',
                'action_performed' => in_array($status, ['Completed', 'For verification'], true) ? 'Inspection, cleaning, calibration where applicable, and functional test completed.' : null,
                'completion_remarks' => $status === 'Completed' ? 'Official inventory demo work order completed.' : null,
                'final_equipment_condition' => $status === 'Beyond repair' ? 'Beyond repair' : (in_array($status, ['Completed', 'For verification'], true) ? 'Good' : null),
                'final_operational_status' => $status === 'Beyond repair' ? 'Unavailable' : (in_array($status, ['Completed', 'For verification'], true) ? 'Available' : null),
                'beyond_repair_reason' => $status === 'Beyond repair' ? 'Repair cost exceeds practical replacement threshold.' : null,
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
                'remarks' => 'Official inventory demo work order.',
            ]));
        }

        $repeatedRepairEquipment = $this->findEquipment($equipment, 'Acer Nitro 5 Laptop');
        for ($repair = 1; $repair <= 3; $repair++) {
            $orders->push(WorkOrder::create([
                'work_order_number' => sprintf('WO-OFF-RPT-%04d', $repair),
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

        $this->findEquipment($equipment, 'Dental Chair')->forceFill(['condition' => 'Defective', 'operational_status' => 'Under inspection'])->save();

        return $orders;
    }

    private function createEvidence($workOrders, User $technician): void
    {
        $workOrders->filter(fn (WorkOrder $order): bool => in_array($order->status, ['Completed', 'For verification'], true))
            ->reject(fn (WorkOrder $order): bool => str_contains($order->title, 'pending after-maintenance evidence'))
            ->each(function (WorkOrder $order, int $index) use ($technician): void {
                foreach (['Before maintenance', 'After maintenance'] as $type) {
                    WorkOrderEvidence::create([
                        'work_order_id' => $order->id,
                        'equipment_id' => $order->equipment_id,
                        'evidence_type' => $type,
                        'image_path' => sprintf('demo/official/work-orders/%s-%s.jpg', $order->work_order_number, str($type)->slug()),
                        'caption' => 'Official inventory demo evidence metadata.',
                        'uploaded_by' => $technician->id,
                        'uploaded_at' => now()->subDays($type === 'Before maintenance' ? 5 : 4)->addMinutes($index),
                    ]);
                }
            });

        $beyondRepair = $workOrders->firstWhere('status', 'Beyond repair');
        if ($beyondRepair) {
            WorkOrderEvidence::create([
                'work_order_id' => $beyondRepair->id,
                'equipment_id' => $beyondRepair->equipment_id,
                'evidence_type' => 'Beyond-repair evidence',
                'image_path' => sprintf('demo/official/work-orders/%s-beyond-repair-1.jpg', $beyondRepair->work_order_number),
                'caption' => 'One beyond-repair evidence item is intentionally seeded for recommendation demo.',
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

    private function createAssetActionRequests($equipment, array $users)
    {
        $samples = [
            ['Biological Safety Cabinet', 'Replacement', 'Replace failed cabinet after safety inspection.', 'Critical', 'Approved'],
            ['Kyocera Copier Machine', 'Major Repair', 'Repair recurring copier feed issue.', 'Normal', 'Submitted'],
            ['Acer Nitro 5 Laptop', 'Replacement', 'Replace repeated-repair laptop unit.', 'High', 'Under Review'],
            ['Fish Breeding Tank', 'Inspection', 'Inspect operating reliability before field deployment.', 'High', 'Rejected'],
            ['Biological Safety Cabinet', 'Disposal', 'Dispose beyond-repair equipment after replacement approval.', 'Critical', 'Completed'],
        ];

        return collect($samples)->map(function (array $sample, int $index) use ($equipment, $users): AssetActionRequest {
            $status = $sample[4];

            return AssetActionRequest::create([
                'request_number' => sprintf('AAR-OFF-%04d', $index + 1),
                'equipment_id' => $this->findEquipment($equipment, $sample[0])->id,
                'requested_by' => $users['staff']->id,
                'reviewed_by' => in_array($status, ['Under Review', 'Approved', 'Rejected', 'Completed'], true) ? $users['administrator']->id : null,
                'approved_by' => in_array($status, ['Approved', 'Completed'], true) ? $users['administrator']->id : null,
                'completed_by' => $status === 'Completed' ? $users['administrator']->id : null,
                'request_type' => $sample[1],
                'reason' => $sample[2],
                'justification' => 'Official inventory demo asset action workflow.',
                'recommended_action' => $sample[1],
                'estimated_cost' => $sample[3] === 'Critical' ? 185000 : 45000,
                'priority' => $sample[3],
                'status' => $status,
                'reviewed_at' => in_array($status, ['Under Review', 'Approved', 'Rejected', 'Completed'], true) ? now()->subDays(6) : null,
                'approved_at' => in_array($status, ['Approved', 'Completed'], true) ? now()->subDays(5) : null,
                'rejected_at' => $status === 'Rejected' ? now()->subDays(4) : null,
                'completed_at' => $status === 'Completed' ? now()->subDays(2) : null,
                'rejection_reason' => $status === 'Rejected' ? 'Inspection can be handled through preventive maintenance first.' : null,
                'remarks' => 'Demo asset action request.',
                'metadata' => ['source' => 'OfficialEquipmentInventorySeeder'],
            ]);
        });
    }

    private function createBudgetPlans($equipment, $assetActions, array $users): void
    {
        $plans = [
            ['Annual Maintenance Budget Plan', 'Prepared', 'Annual preventive and corrective maintenance for official inventory equipment.'],
            ['Equipment Replacement Forecast', 'Approved', 'Replacement planning for critical and beyond-repair demo assets.'],
            ['Laboratory Equipment Upgrade Plan', 'Under Review', 'Upgrade plan for laboratory and scientific equipment.'],
        ];

        foreach ($plans as $planIndex => $planData) {
            $plan = BudgetPlan::create([
                'plan_number' => sprintf('BP-OFF-2026-%04d', $planIndex + 1),
                'title' => $planData[0],
                'fiscal_year' => 2026,
                'budget_date' => now()->subMonths($planIndex)->toDateString(),
                'funds' => ['GAA', 'IGF', 'Trust Fund'][$planIndex % 3],
                'purchase_order_number' => $planIndex === 1 ? 'PO-OFF-2026-0002' : null,
                'description' => $planData[2],
                'status' => $planData[1],
                'prepared_by' => $users['staff']->id,
                'reviewed_by' => in_array($planData[1], ['Under Review', 'Approved'], true) ? $users['administrator']->id : null,
                'approved_by' => $planData[1] === 'Approved' ? $users['administrator']->id : null,
                'reviewed_at' => in_array($planData[1], ['Under Review', 'Approved'], true) ? now()->subDays(3) : null,
                'approved_at' => $planData[1] === 'Approved' ? now()->subDays(2) : null,
                'remarks' => 'Official inventory demo budget plan.',
                'metadata' => ['source' => 'OfficialEquipmentInventorySeeder'],
            ]);

            $equipment->slice($planIndex * 5, 5)->values()->each(function (Equipment $item, int $itemIndex) use ($plan, $assetActions): void {
                BudgetPlanItem::create([
                    'budget_plan_id' => $plan->id,
                    'equipment_id' => $item->id,
                    'asset_action_request_id' => $itemIndex === 0 ? $assetActions->get(0)?->id : null,
                    'item_type' => ['Replacement', 'Major Repair', 'Inspection', 'Procurement', 'Disposal Support'][$itemIndex % 5],
                    'description' => "Budget item for {$item->equipment_name}.",
                    'priority' => ['Critical', 'High', 'Normal', 'Normal', 'Low'][$itemIndex % 5],
                    'estimated_cost' => max(5000, (float) $item->acquisition_cost * ($itemIndex === 0 ? 1.10 : 0.18)),
                    'justification' => 'Demo budget planning item derived from official inventory equipment.',
                    'forecast_reason' => 'Seeded to support budget planning and replacement forecast reports.',
                    'target_period' => 'FY 2026',
                    'status' => ['Approved', 'Proposed', 'Deferred', 'Rejected', 'Completed'][$itemIndex % 5],
                    'metadata' => ['source' => 'OfficialEquipmentInventorySeeder'],
                ]);
            });

            $plan->recalculateTotal();
        }
    }

    private function createNotifications($equipment, $workOrders, array $users): void
    {
        $samples = [
            ['Overdue maintenance alert', 'Preventive maintenance is overdue for selected official inventory equipment.', 'Warning', 'High', 'Preventive Maintenance', $equipment->first()],
            ['Assigned work order', 'An official inventory work order has been assigned to the technician.', 'Information', 'Normal', 'Work Order', $workOrders->firstWhere('status', 'Assigned')],
            ['Pending verification', 'A completed work order is awaiting verification.', 'Reminder', 'High', 'Work Order', $workOrders->firstWhere('status', 'For verification')],
            ['Budget approval required', 'An official inventory budget plan is ready for review.', 'Reminder', 'Normal', 'System', null],
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
                'metadata' => ['source' => 'OfficialEquipmentInventorySeeder'],
            ]);
        }
    }

    private function locationFor(array $row, Location $defaultLocation): Location
    {
        $name = $this->locationNameFromRemarks($row['remarks'] ?? '');
        if ($name === null) {
            return $defaultLocation;
        }

        return Location::firstOrCreate(
            ['name' => $name],
            ['type' => $this->locationType($name), 'description' => 'Location identified from official inventory remarks.', 'is_active' => true],
        );
    }

    private function locationNameFromRemarks(string $remarks): ?string
    {
        $remarks = trim($remarks);
        if ($remarks === '') {
            return null;
        }

        foreach (['Science Lab', 'Conference Room', 'RIS 1', 'RIS 2', 'Comp Lab 1', 'Library', 'Clinic', 'Water Desalination Facility', 'Registrar Office', 'Campus Director Campus Research Office', 'OUP-RIE', 'USC'] as $location) {
            if (strcasecmp($remarks, $location) === 0 || stripos($remarks, $location) !== false) {
                return $location;
            }
        }

        return null;
    }

    private function locationType(string $name): string
    {
        return match (true) {
            str_contains($name, 'Lab') => 'Laboratory',
            str_contains($name, 'Library') => 'Library',
            str_contains($name, 'Clinic') => 'Clinic',
            str_contains($name, 'Office'), in_array($name, ['USC', 'OUP-RIE'], true) => 'Office',
            default => 'Room',
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

    private function brandFor(string $name, string $specification): ?string
    {
        $text = $name.' '.$specification;
        foreach (['Samsung', 'Koppel', 'Kyocera', 'Acer', 'MSI', 'Lenovo', 'Nikon', 'Franklin Electric', 'AIKON'] as $brand) {
            if (stripos($text, $brand) !== false) {
                return $brand;
            }
        }

        return null;
    }

    private function modelFor(string $specification): ?string
    {
        if (preg_match('/Model\s+([^;]+)/i', $specification, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/\b(AN515-57-5620|D5600|IOUC-443PH)\b/i', $specification, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function serialNumberFor(string $text): ?string
    {
        if (preg_match('/SN:\s*([^;]+)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Serial Number\s+([^;]+)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function equipmentRemarks(array $row): string
    {
        return trim(implode("\n", array_filter([
            "Source article: {$row['article']}",
            $row['source_description'] ? "Source description: {$row['source_description']}" : null,
            $row['source_date_acquired'] ? "Source date acquired: {$row['source_date_acquired']}" : null,
            $row['source_unit_value'] ? "Source unit value: {$row['source_unit_value']}" : null,
            $row['remarks'] ? "Source remarks/location: {$row['remarks']}" : null,
            'Source document: Account-Code-10604010.docx',
        ])));
    }

    private function maintenanceType(Equipment $equipment): string
    {
        return match ($equipment->category?->name) {
            'Information and Communication Technology Equipment', 'Office Equipment' => 'Routine cleaning and functionality check',
            'Technical & Scientific Equipment', 'Medical, Dental & Laboratory Equipment' => 'Calibration and inspection',
            'Agricultural, Fishery & Forestry Equipment' => 'Field readiness inspection',
            'Building', 'School Buildings', 'Other Structures' => 'Structural and safety inspection',
            'Books' => 'Inventory condition review',
            default => 'Preventive maintenance',
        };
    }

    private function findEquipment($equipment, string $needle): Equipment
    {
        return $equipment->first(fn (Equipment $item): bool => str_contains(strtolower($item->equipment_name), strtolower($needle)))
            ?? $equipment->firstOrFail();
    }

    private function outputImportSummary($equipment): void
    {
        $locationsCreated = Location::query()->where('description', 'Location identified from official inventory remarks.')->count();
        $this->command?->info('Official equipment inventory import summary');
        $this->command?->line('Account-code sections found: '.count($this->importSummary['account_code_sections_found'] ?? []));
        $this->command?->line('Rows parsed: '.($this->importSummary['rows_parsed'] ?? 0));
        $this->command?->line('Equipment imported: '.$equipment->count());
        $this->command?->line('Equipment updated: 0');
        $this->command?->line('Rows skipped: '.($this->importSummary['rows_skipped'] ?? 0));
        $this->command?->line('Rows with missing property numbers: '.($this->importSummary['missing_property_numbers'] ?? 0));
        $this->command?->line('Rows with unparsed dates: '.count($this->importSummary['unparsed_dates'] ?? []));
        $this->command?->line('Rows with malformed unit values: '.count($this->importSummary['malformed_unit_values'] ?? []));
        $this->command?->line('Duplicate/conflicting rows: '.count($this->importSummary['duplicate_conflicts'] ?? []));
        $this->command?->line('Locations created: '.$locationsCreated);
        $this->command?->line('Workflow demo records created: schedules, requests, work orders, evidence metadata, recommendations, lifecycle profiles, asset actions, budget plans, notifications.');
    }
}
