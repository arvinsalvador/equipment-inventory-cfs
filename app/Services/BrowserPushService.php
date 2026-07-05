<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use App\Notifications\BrowserPushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class BrowserPushService
{
    public function __construct(
        private readonly BrowserPushPreparationService $preparationService,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('webpush.vapid.public_key');
    }

    /**
     * @return array{attempted:bool,sent:int,reason:?string}
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        string $category = 'System',
        string $url = '/admin/mobile-technician-dashboard',
        string $tag = 'seims-browser-push',
    ): array {
        if (! $this->isConfigured()) {
            return ['attempted' => false, 'sent' => 0, 'reason' => 'VAPID keys are not configured'];
        }

        if (! $this->preparationService->userAllowsBrowserPush($user, $category)) {
            return ['attempted' => false, 'sent' => 0, 'reason' => 'Browser push preference disabled'];
        }

        $subscriptionCount = $this->preparationService->activeSubscriptionCount($user);

        if ($subscriptionCount === 0) {
            return ['attempted' => false, 'sent' => 0, 'reason' => 'No active browser push subscriptions'];
        }

        try {
            Notification::send($user, new BrowserPushNotification($title, $body, $url, $tag));
        } catch (Throwable $exception) {
            Log::warning('Browser push delivery failed.', [
                'user_id' => $user->id,
                'category' => $category,
                'exception' => $exception->getMessage(),
            ]);

            return ['attempted' => true, 'sent' => 0, 'reason' => $exception->getMessage()];
        }

        return ['attempted' => true, 'sent' => $subscriptionCount, 'reason' => null];
    }

    /**
     * @return array{attempted:bool,sent:int,reason:?string}
     */
    public function sendForNotification(SystemNotification $notification): array
    {
        if ($notification->user !== null) {
            return $this->sendToUser(
                $notification->user,
                $notification->title,
                $notification->message,
                $notification->category,
                $notification->action_url ?: '/admin/mobile-technician-dashboard',
                'seims-notification-'.$notification->id,
            );
        }

        $sent = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Administrator'))
            ->get()
            ->sum(fn (User $user): int => $this->sendToUser(
                $user,
                $notification->title,
                $notification->message,
                $notification->category,
                $notification->action_url ?: '/admin/mobile-technician-dashboard',
                'seims-notification-'.$notification->id,
            )['sent']);

        return [
            'attempted' => $sent > 0,
            'sent' => $sent,
            'reason' => $sent > 0 ? null : 'No eligible browser push recipients',
        ];
    }

    /**
     * @return array{attempted:bool,sent:int,reason:?string}
     */
    public function sendTestToUser(User $user): array
    {
        return $this->sendToUser(
            $user,
            'SEIMS Test Notification',
            'Browser Push Notifications are working correctly.',
            'System',
            '/admin/mobile-technician-dashboard',
            'seims-test-notification',
        );
    }
}
