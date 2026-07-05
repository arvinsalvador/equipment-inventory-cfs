<?php

namespace App\Services;

use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaintenanceRecommendationApprovalService
{
    /**
     * @return array{status: string, message: string, maintenance_request: MaintenanceRequest|null, reason: string|null}
     */
    public function approve(MaintenanceRecommendation $recommendation, User $user, ?string $notes = null): array
    {
        return DB::transaction(function () use ($recommendation, $user, $notes): array {
            $recommendation = MaintenanceRecommendation::query()
                ->lockForUpdate()
                ->findOrFail($recommendation->id);

            if ($recommendation->linked_maintenance_request_id !== null) {
                $request = $recommendation->linkedMaintenanceRequest()->first();

                if ($recommendation->isActionPending()) {
                    $this->markApproved($recommendation, $user, $notes);
                }

                return [
                    'status' => 'warning',
                    'message' => 'Maintenance request already exists.',
                    'maintenance_request' => $request,
                    'reason' => null,
                ];
            }

            $this->markApproved($recommendation, $user, $notes);

            $reason = $this->nonActionableReason($recommendation);

            if ($reason !== null) {
                return [
                    'status' => 'info',
                    'message' => 'Recommendation approved, but no maintenance request was created because: '.$reason,
                    'maintenance_request' => null,
                    'reason' => $reason,
                ];
            }

            $request = MaintenanceRequest::create([
                'equipment_id' => $recommendation->equipment_id,
                'submitted_by' => $user->id,
                'problem_description' => $this->problemDescription($recommendation),
                'severity' => $this->severity($recommendation),
                'status' => 'Submitted',
                'remarks' => 'Created from approved recommendation #'.$recommendation->id.'.',
            ]);

            $recommendation->forceFill([
                'linked_maintenance_request_id' => $request->id,
            ])->save();

            app(AuditLogService::class)->log('maintenance_request_created', 'AI Recommendation', "Maintenance request {$request->request_number} created from recommendation {$recommendation->title}.", $user, $recommendation, null, null, [
                'maintenance_request_id' => $request->id,
            ]);

            return [
                'status' => 'success',
                'message' => 'Recommendation approved and maintenance request created.',
                'maintenance_request' => $request->refresh(),
                'reason' => null,
            ];
        });
    }

    private function markApproved(MaintenanceRecommendation $recommendation, User $user, ?string $notes): void
    {
        if ($recommendation->isActionPending()) {
            $recommendation->approveAction($user, $notes);
        }

        $recommendation->forceFill([
            'status' => 'Approved',
            'reviewed_by' => $recommendation->reviewed_by ?? $user->id,
            'reviewed_at' => $recommendation->reviewed_at ?? now(),
        ])->save();
    }

    private function nonActionableReason(MaintenanceRecommendation $recommendation): ?string
    {
        $actionType = $recommendation->getSuggestedActionType();

        if ($actionType === 'monitor_only') {
            return 'the suggested action is monitor only.';
        }

        if (! in_array($recommendation->rule_key, MaintenanceRecommendation::RULE_KEYS, true)) {
            return 'the recommendation rule is not mapped to a maintenance request workflow.';
        }

        return null;
    }

    private function problemDescription(MaintenanceRecommendation $recommendation): string
    {
        return implode("\n\n", array_filter([
            $recommendation->title,
            $recommendation->explanation,
            'Recommended action: '.$recommendation->recommended_action,
            'Rule: '.$recommendation->rule_key,
        ]));
    }

    private function severity(MaintenanceRecommendation $recommendation): string
    {
        return match ($recommendation->risk_level) {
            'Critical' => 'Critical',
            'High' => 'High',
            'Low' => 'Low',
            default => 'Moderate',
        };
    }
}
