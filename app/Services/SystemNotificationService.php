<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentLifecycleProfile;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;

class SystemNotificationService
{
    private ?string $lastSkippedReason = null;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createNotification(
        ?User $user,
        string $title,
        string $message,
        string $type = 'Information',
        string $priority = 'Normal',
        string $category = 'System',
        ?Model $related = null,
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?\DateTimeInterface $expiresAt = null,
    ): ?SystemNotification {
        $this->lastSkippedReason = null;

        if ($user !== null && ! $user->wantsNotificationCategory($category)) {
            $this->lastSkippedReason = 'preference';

            return null;
        }

        $relatedType = $related ? $related::class : null;
        $relatedId = $related?->getKey();

        $duplicate = SystemNotification::query()
            ->unread()
            ->active()
            ->where('category', $category)
            ->where('title', $title)
            ->where('user_id', $user?->id)
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->exists();

        if ($duplicate) {
            $this->lastSkippedReason = 'duplicate';

            return null;
        }

        return SystemNotification::create([
            'user_id' => $user?->id,
            'title' => $title,
            'message' => $message,
            'notification_type' => $type,
            'priority' => $priority,
            'category' => $category,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action_url' => $actionUrl,
            'generated_at' => now(),
            'expires_at' => $expiresAt,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createForUser(
        User $user,
        string $title,
        string $message,
        string $type = 'Information',
        string $priority = 'Normal',
        string $category = 'System',
        ?Model $related = null,
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?\DateTimeInterface $expiresAt = null,
    ): ?SystemNotification {
        return $this->createNotification($user, $title, $message, $type, $priority, $category, $related, $actionUrl, $metadata, $expiresAt);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createGlobal(
        string $title,
        string $message,
        string $type = 'Information',
        string $priority = 'Normal',
        string $category = 'System',
        ?Model $related = null,
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?\DateTimeInterface $expiresAt = null,
    ): ?SystemNotification {
        return $this->createNotification(null, $title, $message, $type, $priority, $category, $related, $actionUrl, $metadata, $expiresAt);
    }

    public function markAsRead(SystemNotification $notification): SystemNotification
    {
        return $notification->markAsRead();
    }

    public function markAllAsRead(User $user): int
    {
        return SystemNotification::query()
            ->visibleTo($user)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateAll(): array
    {
        return $this->mergeSummaries([
            $this->generateMaintenanceNotifications(),
            $this->generateWorkOrderNotifications(),
            $this->generateMaintenanceRequestNotifications(),
            $this->generateAiRecommendationNotifications(),
            $this->generateLifecycleNotifications(),
            $this->generateWarrantyNotifications(),
            $this->generateEvidenceNotifications(),
        ]);
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateMaintenanceNotifications(): array
    {
        $summary = $this->summary('Preventive Maintenance');

        MaintenanceSchedule::query()
            ->with(['equipment', 'assignedUser'])
            ->incomplete()
            ->whereDate('scheduled_date', '<=', today()->addDays(7))
            ->get()
            ->each(function (MaintenanceSchedule $schedule) use (&$summary): void {
                $displayStatus = $schedule->displayStatus();
                $priority = match ($displayStatus) {
                    'Overdue' => 'Critical',
                    'Due today' => 'High',
                    default => 'Normal',
                };

                $this->record(
                    $summary,
                    $this->createNotification(
                        $schedule->assignedUser,
                        "{$displayStatus}: {$schedule->maintenance_type}",
                        "{$schedule->equipment->equipment_code} is scheduled for {$schedule->maintenance_type} on {$schedule->scheduled_date->format('M d, Y')}.",
                        $displayStatus === 'Overdue' ? 'Critical' : 'Reminder',
                        $priority,
                        'Preventive Maintenance',
                        $schedule,
                        $this->adminUrl('/maintenance-schedules/'.$schedule->id)
                    )
                );
            });

        return $summary;
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateWorkOrderNotifications(): array
    {
        $summary = $this->summary('Work Order');

        WorkOrder::query()
            ->with(['equipment', 'assignedTo'])
            ->whereIn('status', ['Assigned', 'Available', 'For verification', 'On hold', 'Awaiting parts', 'Completed'])
            ->get()
            ->each(function (WorkOrder $workOrder) use (&$summary): void {
                [$title, $priority, $type] = match ($workOrder->status) {
                    'Assigned' => ['Work order assigned', 'Normal', 'Information'],
                    'Available' => ['Work order available', 'Normal', 'Information'],
                    'For verification' => ['Work order awaiting verification', 'High', 'Warning'],
                    'On hold' => ['Work order on hold', 'High', 'Warning'],
                    'Awaiting parts' => ['Work order awaiting parts', 'High', 'Warning'],
                    default => ['Work order completed', 'Normal', 'Information'],
                };

                $this->record(
                    $summary,
                    $this->createNotification(
                        $workOrder->assignedTo,
                        "{$title}: {$workOrder->work_order_number}",
                        "{$workOrder->work_order_number} for {$workOrder->equipment->equipment_code} is {$workOrder->status}.",
                        $type,
                        $priority,
                        'Work Order',
                        $workOrder,
                        $this->adminUrl('/work-orders/'.$workOrder->id)
                    )
                );
            });

        return $summary;
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateMaintenanceRequestNotifications(): array
    {
        $summary = $this->summary('Maintenance Request');

        MaintenanceRequest::query()
            ->with(['equipment', 'submittedBy'])
            ->whereIn('status', ['Submitted', 'Approved', 'Rejected', 'Converted'])
            ->get()
            ->each(function (MaintenanceRequest $request) use (&$summary): void {
                $priority = $request->status === 'Rejected' ? 'High' : 'Normal';

                $this->record(
                    $summary,
                    $this->createNotification(
                        $request->submittedBy,
                        "Maintenance request {$request->status}: {$request->request_number}",
                        "{$request->request_number} for {$request->equipment->equipment_code} is {$request->status}.",
                        $request->status === 'Rejected' ? 'Warning' : 'Information',
                        $priority,
                        'Maintenance Request',
                        $request,
                        $this->adminUrl('/maintenance-requests/'.$request->id)
                    )
                );
            });

        return $summary;
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateAiRecommendationNotifications(): array
    {
        $summary = $this->summary('AI Recommendation');

        MaintenanceRecommendation::query()
            ->with('equipment')
            ->unresolved()
            ->where(fn ($query) => $query
                ->whereIn('risk_level', ['High', 'Critical'])
                ->orWhereIn('action_status', ['Pending', 'Approved']))
            ->get()
            ->each(function (MaintenanceRecommendation $recommendation) use (&$summary): void {
                [$title, $priority, $type] = match (true) {
                    $recommendation->risk_level === 'Critical' => ['Critical recommendation', 'Critical', 'Critical'],
                    $recommendation->risk_level === 'High' => ['High recommendation', 'High', 'Warning'],
                    $recommendation->action_status === 'Approved' => ['Approved recommendation action awaiting execution', 'High', 'Warning'],
                    default => ['Pending recommendation action', 'Normal', 'Information'],
                };

                $this->record(
                    $summary,
                    $this->createGlobal(
                        "{$title}: {$recommendation->title}",
                        "{$recommendation->equipment->equipment_code}: {$recommendation->getSuggestedActionLabel()}.",
                        $type,
                        $priority,
                        'AI Recommendation',
                        $recommendation,
                        $this->adminUrl('/maintenance-recommendations/'.$recommendation->id)
                    )
                );
            });

        return $summary;
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateLifecycleNotifications(): array
    {
        $summary = $this->summary('Equipment Lifecycle');

        EquipmentLifecycleProfile::query()
            ->with('equipment')
            ->where(fn ($query) => $query
                ->where('lifecycle_status', 'Replacement Candidate')
                ->orWhere('health_grade', 'Critical')
                ->orWhere('health_score', '<', 40)
                ->orWhereBetween('estimated_remaining_life_months', [0, 12]))
            ->get()
            ->each(function (EquipmentLifecycleProfile $profile) use (&$summary): void {
                [$title, $priority] = match (true) {
                    $profile->isReplacementCandidate() => ['Replacement candidate', 'Critical'],
                    $profile->isCritical() => ['Critical health score', 'Critical'],
                    default => ['Near end-of-life equipment', 'High'],
                };

                $this->record(
                    $summary,
                    $this->createGlobal(
                        "{$title}: {$profile->equipment->equipment_code}",
                        "{$profile->equipment->equipment_name} has lifecycle status {$profile->lifecycle_status} and health score {$profile->health_score}.",
                        $priority === 'Critical' ? 'Critical' : 'Warning',
                        $priority,
                        'Equipment Lifecycle',
                        $profile,
                        $this->adminUrl('/equipment/'.$profile->equipment_id)
                    )
                );
            });

        return $summary;
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateWarrantyNotifications(): array
    {
        $summary = $this->summary('Warranty');

        Equipment::query()
            ->active()
            ->whereNotNull('warranty_expiration_date')
            ->whereDate('warranty_expiration_date', '<=', today()->addDays(30))
            ->get()
            ->each(function (Equipment $equipment) use (&$summary): void {
                $days = today()->diffInDays($equipment->warranty_expiration_date, false);
                [$title, $priority] = match (true) {
                    $days < 0 => ['Warranty expired', 'Critical'],
                    $days <= 7 => ['Warranty expires within 7 days', 'High'],
                    default => ['Warranty expires within 30 days', 'Normal'],
                };

                $this->record(
                    $summary,
                    $this->createGlobal(
                        "{$title}: {$equipment->equipment_code}",
                        "{$equipment->equipment_name} warranty date is {$equipment->warranty_expiration_date->format('M d, Y')}.",
                        $priority === 'Critical' ? 'Critical' : 'Reminder',
                        $priority,
                        'Warranty',
                        $equipment,
                        $this->adminUrl('/equipment/'.$equipment->id)
                    )
                );
            });

        return $summary;
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    public function generateEvidenceNotifications(): array
    {
        $summary = $this->summary('Evidence');

        WorkOrder::query()
            ->with(['equipment', 'assignedTo'])
            ->whereIn('status', ['In progress', 'For verification', 'Beyond repair'])
            ->get()
            ->each(function (WorkOrder $workOrder) use (&$summary): void {
                if ($workOrder->status === 'Beyond repair' && ! $workOrder->hasRequiredBeyondRepairEvidence()) {
                    $this->recordEvidence($summary, $workOrder, 'Beyond-repair work order missing required evidence', 'Critical', 'Critical');

                    return;
                }

                if ($workOrder->isForVerification() && ! $workOrder->hasRequiredCompletionEvidence()) {
                    $this->recordEvidence($summary, $workOrder, 'Work order for verification has missing evidence', 'High', 'Warning');

                    return;
                }

                if ($workOrder->status === 'In progress' && ! $workOrder->hasAfterMaintenanceEvidence()) {
                    $this->recordEvidence($summary, $workOrder, 'Work order missing after-maintenance evidence', 'High', 'Warning');
                }
            });

        return $summary;
    }

    private function recordEvidence(array &$summary, WorkOrder $workOrder, string $title, string $priority, string $type): void
    {
        $this->record(
            $summary,
            $this->createNotification(
                $workOrder->assignedTo,
                "{$title}: {$workOrder->work_order_number}",
                "{$workOrder->work_order_number} for {$workOrder->equipment->equipment_code} needs evidence before closure.",
                $type,
                $priority,
                'Evidence',
                $workOrder,
                $this->adminUrl('/work-orders/'.$workOrder->id)
            )
        );
    }

    /**
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    private function summary(string $category): array
    {
        return [
            'created' => 0,
            'duplicates_skipped' => 0,
            'categories_checked' => [$category],
        ];
    }

    private function record(array &$summary, ?SystemNotification $notification): void
    {
        if ($notification !== null) {
            $summary['created']++;

            return;
        }

        if ($this->lastSkippedReason === 'duplicate') {
            $summary['duplicates_skipped']++;
        }
    }

    /**
     * @param  array<int, array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}>  $summaries
     * @return array{created:int,duplicates_skipped:int,categories_checked:array<int, string>}
     */
    private function mergeSummaries(array $summaries): array
    {
        return [
            'created' => array_sum(array_column($summaries, 'created')),
            'duplicates_skipped' => array_sum(array_column($summaries, 'duplicates_skipped')),
            'categories_checked' => array_values(array_unique(array_merge(...array_column($summaries, 'categories_checked')))),
        ];
    }

    private function adminUrl(string $path): string
    {
        return url('/admin'.(str_starts_with($path, '/') ? $path : '/'.$path));
    }
}
