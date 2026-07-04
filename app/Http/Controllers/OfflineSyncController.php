<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\WorkOrder;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OfflineSyncController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user() !== null, 401);

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in([
                'maintenance_request.create',
                'work_order.completion_update',
                'evidence.metadata',
                'equipment.status_update',
                'maintenance_note.create',
                'inspection_note.create',
                'equipment_note.create',
            ])],
            'payload' => ['required', 'array'],
            'base_updated_at' => ['nullable', 'date'],
        ]);

        $response = match ($data['type']) {
            'maintenance_request.create' => $this->syncMaintenanceRequest($request, $data),
            'work_order.completion_update' => $this->syncWorkOrderCompletion($request, $data),
            'evidence.metadata' => $this->syncEvidenceMetadata($request, $data),
            'equipment.status_update' => $this->syncEquipmentStatus($request, $data),
            'maintenance_note.create', 'inspection_note.create' => $this->syncWorkOrderNote($request, $data),
            'equipment_note.create' => $this->syncEquipmentNote($request, $data),
        };

        $this->auditLogService->log('submitted', 'PWA Offline Sync', "Offline sync action {$data['type']} submitted.", $request->user(), metadata: [
            'client_id' => $data['client_id'],
            'type' => $data['type'],
        ]);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMaintenanceRequest(Request $request, array $data): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', MaintenanceRequest::class);

        $payload = validator($data['payload'], [
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'problem_description' => ['required', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(MaintenanceRequest::SEVERITIES)],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $maintenanceRequest = MaintenanceRequest::create([
            ...$payload,
            'submitted_by' => $request->user()->id,
            'status' => 'Submitted',
        ]);

        return response()->json([
            'status' => 'synced',
            'record_type' => 'maintenance_request',
            'record_id' => $maintenanceRequest->id,
            'client_id' => $data['client_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncWorkOrderCompletion(Request $request, array $data): JsonResponse
    {
        $payload = validator($data['payload'], [
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'findings' => ['nullable', 'string', 'max:5000'],
            'action_performed' => ['nullable', 'string', 'max:5000'],
            'completion_remarks' => ['nullable', 'string', 'max:5000'],
            'final_equipment_condition' => ['nullable', 'string', 'max:255'],
            'final_operational_status' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $workOrder = WorkOrder::findOrFail($payload['work_order_id']);
        Gate::forUser($request->user())->authorize('updateAssigned', $workOrder);
        $this->abortIfServerChanged($workOrder, $data['base_updated_at'] ?? null);

        $workOrder->update(collect($payload)
            ->except('work_order_id')
            ->filter(fn ($value): bool => $value !== null)
            ->all());

        return response()->json([
            'status' => 'synced',
            'record_type' => 'work_order',
            'record_id' => $workOrder->id,
            'client_id' => $data['client_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncEvidenceMetadata(Request $request, array $data): JsonResponse
    {
        $payload = validator($data['payload'], [
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'evidence_type' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:5000'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $workOrder = WorkOrder::findOrFail($payload['work_order_id']);
        Gate::forUser($request->user())->authorize('uploadEvidence', $workOrder);
        $this->abortIfServerChanged($workOrder, $data['base_updated_at'] ?? null);

        return response()->json([
            'status' => 'synced',
            'record_type' => 'work_order_evidence_metadata',
            'record_id' => $workOrder->id,
            'upload_deferred' => true,
            'message' => 'Evidence metadata synchronized. Image upload remains deferred until online upload is available.',
            'client_id' => $data['client_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncEquipmentStatus(Request $request, array $data): JsonResponse
    {
        $payload = validator($data['payload'], [
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'condition' => ['nullable', Rule::in(Equipment::CONDITIONS)],
            'operational_status' => ['nullable', Rule::in(Equipment::OPERATIONAL_STATUSES)],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $equipment = Equipment::findOrFail($payload['equipment_id']);
        Gate::forUser($request->user())->authorize('update', $equipment);
        $this->abortIfServerChanged($equipment, $data['base_updated_at'] ?? null);

        $equipment->update(collect($payload)
            ->except('equipment_id')
            ->filter(fn ($value): bool => $value !== null)
            ->all());

        return response()->json([
            'status' => 'synced',
            'record_type' => 'equipment',
            'record_id' => $equipment->id,
            'client_id' => $data['client_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncWorkOrderNote(Request $request, array $data): JsonResponse
    {
        $payload = validator($data['payload'], [
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'note' => ['required', 'string', 'max:5000'],
        ])->validate();

        $workOrder = WorkOrder::findOrFail($payload['work_order_id']);
        Gate::forUser($request->user())->authorize('updateAssigned', $workOrder);
        $this->abortIfServerChanged($workOrder, $data['base_updated_at'] ?? null);

        $workOrder->update([
            'remarks' => trim(($workOrder->remarks ? $workOrder->remarks.PHP_EOL.PHP_EOL : '').$payload['note']),
        ]);

        return response()->json([
            'status' => 'synced',
            'record_type' => 'work_order_note',
            'record_id' => $workOrder->id,
            'client_id' => $data['client_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncEquipmentNote(Request $request, array $data): JsonResponse
    {
        $payload = validator($data['payload'], [
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'note' => ['required', 'string', 'max:5000'],
        ])->validate();

        $equipment = Equipment::findOrFail($payload['equipment_id']);
        Gate::forUser($request->user())->authorize('update', $equipment);
        $this->abortIfServerChanged($equipment, $data['base_updated_at'] ?? null);

        $equipment->update([
            'remarks' => trim(($equipment->remarks ? $equipment->remarks.PHP_EOL.PHP_EOL : '').$payload['note']),
        ]);

        return response()->json([
            'status' => 'synced',
            'record_type' => 'equipment_note',
            'record_id' => $equipment->id,
            'client_id' => $data['client_id'],
        ]);
    }

    private function abortIfServerChanged(object $record, ?string $baseUpdatedAt): void
    {
        if (! $baseUpdatedAt || ! isset($record->updated_at) || $record->updated_at === null) {
            return;
        }

        if ($record->updated_at->greaterThan(Carbon::parse($baseUpdatedAt)->addSecond())) {
            $this->auditLogService->log('conflict_detected', 'PWA Offline Sync', 'Offline sync conflict detected.', request()->user(), $record, metadata: [
                'base_updated_at' => $baseUpdatedAt,
                'server_updated_at' => $record->updated_at?->toDateTimeString(),
            ]);

            abort(response()->json([
                'status' => 'conflict',
                'message' => 'Server version changed. Please review before resubmitting.',
            ], 409));
        }
    }
}
