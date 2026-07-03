<?php

namespace App\Services;

use App\Models\BrowserPushSubscription;
use App\Models\User;

class BrowserPushPreparationService
{
    public function userAllowsBrowserPush(User $user, ?string $category = null): bool
    {
        $preference = $user->notificationPreference;

        if ($preference === null || ! $preference->browser_push_enabled) {
            return false;
        }

        if ($category === null) {
            return true;
        }

        if ($category === 'Critical') {
            return $preference->wantsCriticalBrowserPush();
        }

        return $preference->wantsBrowserPushCategory($category);
    }

    public function userHasActiveSubscription(User $user): bool
    {
        return $user->activeBrowserPushSubscriptions()->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function registerSubscription(User $user, array $payload): BrowserPushSubscription
    {
        return BrowserPushSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $payload['endpoint'],
            ],
            [
                'public_key' => $payload['public_key'] ?? null,
                'auth_token' => $payload['auth_token'] ?? null,
                'content_encoding' => $payload['content_encoding'] ?? null,
                'user_agent' => $payload['user_agent'] ?? null,
                'device_name' => $payload['device_name'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
                'revoked_at' => null,
                'metadata' => $payload['metadata'] ?? null,
            ]
        )->refresh();
    }

    public function revokeSubscription(BrowserPushSubscription $subscription): BrowserPushSubscription
    {
        return $subscription->revoke();
    }

    /**
     * @return array{enabled:bool,active_subscriptions:int,ready:bool,status:string}
     */
    public function getReadinessForUser(User $user): array
    {
        $enabled = $this->userAllowsBrowserPush($user);
        $activeSubscriptions = $this->activeSubscriptionCount($user);

        return [
            'enabled' => $enabled,
            'active_subscriptions' => $activeSubscriptions,
            'ready' => $enabled && $activeSubscriptions > 0,
            'status' => match (true) {
                ! $enabled => 'Browser push disabled',
                $activeSubscriptions === 0 => 'No active browser subscriptions',
                default => 'Ready for future browser push delivery',
            },
        ];
    }

    public function activeSubscriptionCount(User $user): int
    {
        return $user->activeBrowserPushSubscriptions()->count();
    }
}
