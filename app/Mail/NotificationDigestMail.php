<?php

namespace App\Mail;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;

class NotificationDigestMail extends Mailable
{
    /**
     * @param  Collection<int, SystemNotification>  $notifications
     */
    public function __construct(
        public User $recipient,
        public string $period,
        public Collection $notifications,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(str($this->period)->title().' Notification Digest')
            ->view('emails.notifications.digest')
            ->with([
                'systemName' => config('app.name', 'AI Based Equipment Inventory and Maintenance'),
                'recipient' => $this->recipient,
                'period' => $this->period,
                'notifications' => $this->notifications,
                'groupedNotifications' => $this->notifications->groupBy('priority'),
            ]);
    }
}
