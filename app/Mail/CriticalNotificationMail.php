<?php

namespace App\Mail;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Mail\Mailable;

class CriticalNotificationMail extends Mailable
{
    public function __construct(
        public SystemNotification $notification,
        public User $recipient,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Critical Alert: '.$this->notification->title)
            ->view('emails.notifications.critical')
            ->with([
                'systemName' => config('app.name', 'AI Based Equipment Inventory and Maintenance'),
                'notification' => $this->notification,
                'recipient' => $this->recipient,
            ]);
    }
}
