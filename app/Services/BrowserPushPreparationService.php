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
        $publicKey = $payload['public_key'] ?? data_get($payload, 'keys.p256dh');
        $authToken = $payload['auth_token'] ?? data_get($payload, 'keys.auth');
        $contentEncoding = $payload['content_encoding'] ?? data_get($payload, 'metadata.content_encoding') ?? 'aes128gcm';

        return BrowserPushSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint_hash' => hash('sha256', $payload['endpoint']),
            ],
            [
                'endpoint' => $payload['endpoint'],
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => $contentEncoding,
                'user_agent' => $payload['user_agent'] ?? null,
                'device_name' => $payload['device_name'] ?? null,
                'browser' => $payload['browser'] ?? data_get($payload, 'metadata.browser'),
                'platform' => $payload['platform'] ?? data_get($payload, 'metadata.platform'),
                'is_active' => true,
                'last_seen_at' => now(),
                'last_used_at' => now(),
                'revoked_at' => null,
                'metadata' => $payload['metadata'] ?? null,
            ]
        )->refresh();
    }

    public function revokeSubscription(BrowserPushSubscription $subscription): BrowserPushSubscription
    {
        return $subscription->revoke();
    }

    public function revokeCurrentSubscription(User $user, string $endpoint): ?BrowserPushSubscription
    {
        $subscription = BrowserPushSubscription::query()
            ->forUser($user)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->first();

        return $subscription?->revoke();
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
            'ready' => app(BrowserPushService::class)->isConfigured() && $enabled && $activeSubscriptions > 0,
            'status' => match (true) {
                ! app(BrowserPushService::class)->isConfigured() => 'VAPID keys missing',
                ! $enabled => 'Browser push disabled',
                $activeSubscriptions === 0 => 'No active browser subscriptions',
                default => 'Ready for browser push delivery',
            },
        ];
    }

    public function activeSubscriptionCount(User $user): int
    {
        return $user->activeBrowserPushSubscriptions()->count();
    }
}
