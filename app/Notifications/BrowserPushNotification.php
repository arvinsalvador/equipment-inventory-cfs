<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BrowserPushNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url = '/admin/mobile-technician-dashboard',
        private readonly string $tag = 'seims-browser-push',
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icons/pwa-icon.svg')
            ->badge('/icons/pwa-maskable.svg')
            ->tag($this->tag)
            ->data([
                'url' => $this->url,
                'notification_id' => $notification->id,
            ]);
    }
}
