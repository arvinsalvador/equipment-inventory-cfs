<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $action,
        string $module,
        string $description,
        ?User $user = null,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): ?AuditLog {
        try {
            $request ??= request();

            return AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'module' => $module,
                'entity_type' => $entity ? $entity::class : null,
                'entity_id' => $entity?->getKey(),
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
                'request_id' => $request?->headers->get('X-Request-Id'),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Audit logging failed.', [
                'action' => $action,
                'module' => $module,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    public function logCreate(Model $entity, string $module, ?User $user = null, ?array $values = null, ?string $description = null): ?AuditLog
    {
        return $this->log('created', $module, $description ?? class_basename($entity).' created.', $user, $entity, null, $values ?? $entity->getAttributes());
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function logUpdate(Model $entity, string $module, ?User $user = null, ?array $oldValues = null, ?array $newValues = null, ?string $description = null): ?AuditLog
    {
        return $this->log('updated', $module, $description ?? class_basename($entity).' updated.', $user, $entity, $oldValues, $newValues);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     */
    public function logDelete(Model $entity, string $module, ?User $user = null, ?array $oldValues = null, ?string $description = null): ?AuditLog
    {
        return $this->log('deleted', $module, $description ?? class_basename($entity).' deleted.', $user, $entity, $oldValues ?? $entity->getAttributes());
    }

    public function logView(string $module, string $description, ?User $user = null, ?Model $entity = null): ?AuditLog
    {
        return $this->log('viewed', $module, $description, $user, $entity);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function logExport(string $module, string $description, ?User $user = null, ?array $metadata = null): ?AuditLog
    {
        return $this->log('exported', $module, $description, $user, null, null, null, $metadata);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function logApproval(string $module, string $description, ?User $user = null, ?Model $entity = null, ?array $metadata = null): ?AuditLog
    {
        return $this->log('approved', $module, $description, $user, $entity, null, null, $metadata);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function logStatusChange(Model $entity, string $module, ?string $oldStatus, ?string $newStatus, ?User $user = null, ?string $description = null, ?array $metadata = null): ?AuditLog
    {
        return $this->log(
            'status_changed',
            $module,
            $description ?? class_basename($entity)." status changed from {$oldStatus} to {$newStatus}.",
            $user,
            $entity,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            $metadata,
        );
    }

    public function logLogin(?User $user, bool $successful = true): ?AuditLog
    {
        return $this->log($successful ? 'login' : 'failed_login', 'Authentication', $successful ? 'User logged in.' : 'Failed login attempt.', $user);
    }

    public function logLogout(?User $user): ?AuditLog
    {
        return $this->log('logout', 'Authentication', 'User logged out.', $user);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function logSystem(string $module, string $description, ?array $metadata = null): ?AuditLog
    {
        return $this->log('system', $module, $description, null, null, null, null, $metadata);
    }
}
