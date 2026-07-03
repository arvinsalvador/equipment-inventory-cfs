<?php

namespace App\Services;

use App\Mail\CriticalNotificationMail;
use App\Mail\NotificationDigestMail;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationEmailService
{
    private bool $lastSendFailed = false;

    /**
     * @return array{users_checked:int,emails_sent:int,skipped:int,failures:int}
     */
    public function sendImmediateCriticalEmails(): array
    {
        $summary = $this->emailSummary();

        SystemNotification::query()
            ->critical()
            ->unread()
            ->active()
            ->whereNull('email_sent_at')
            ->get()
            ->each(function (SystemNotification $notification) use (&$summary): void {
                if ($notification->user_id !== null) {
                    $summary['users_checked']++;
                    $sent = $this->sendNotificationEmail($notification);
                    $this->recordSendResult($summary, $sent);

                    return;
                }

                User::query()
                    ->whereHas('notificationPreference', fn ($query) => $query
                        ->where('email_notifications_enabled', true)
                        ->where('immediate_critical_email_enabled', true))
                    ->get()
                    ->each(function (User $user) use ($notification, &$summary): void {
                        $summary['users_checked']++;
                        $sent = $this->sendNotificationEmail($notification, $user);
                        $this->recordSendResult($summary, $sent);
                    });
            });

        return $summary;
    }

    /**
     * @return array{users_checked:int,emails_sent:int,skipped:int,failures:int}
     */
    public function sendDailyDigests(): array
    {
        return $this->sendDigests('daily');
    }

    /**
     * @return array{users_checked:int,emails_sent:int,skipped:int,failures:int}
     */
    public function sendWeeklyDigests(): array
    {
        return $this->sendDigests('weekly');
    }

    /**
     * @return array{users_checked:int,emails_sent:int,skipped:int,failures:int}
     */
    public function sendDigestForUser(User $user, string $period): array
    {
        $summary = $this->emailSummary();
        $summary['users_checked'] = 1;

        $preference = $user->notificationPreference;
        $enabled = $period === 'weekly'
            ? $preference?->wantsWeeklyDigestEmail()
            : $preference?->wantsDailyDigestEmail();

        if (! $enabled) {
            $summary['skipped']++;

            return $summary;
        }

        $notifications = $this->digestNotifications($user);

        if ($notifications->isEmpty()) {
            $summary['skipped']++;

            return $summary;
        }

        try {
            Mail::to($user->email)->send(new NotificationDigestMail($user, $period, $notifications));
            $summary['emails_sent']++;
        } catch (Throwable) {
            $summary['failures']++;
        }

        return $summary;
    }

    public function sendNotificationEmail(SystemNotification $notification, ?User $recipient = null): bool
    {
        $this->lastSendFailed = false;

        if ($notification->wasEmailed()) {
            return false;
        }

        if (! $notification->isUnread() || ! in_array('Critical', [$notification->priority, $notification->notification_type], true)) {
            return false;
        }

        $recipient ??= $notification->user;

        if ($recipient === null || ! $recipient->notificationPreference?->wantsImmediateCriticalEmail()) {
            return false;
        }

        $notification->forceFill([
            'email_delivery_attempts' => $notification->email_delivery_attempts + 1,
        ])->save();

        try {
            Mail::to($recipient->email)->send(new CriticalNotificationMail($notification, $recipient));

            $notification->forceFill([
                'email_sent_at' => now(),
                'email_failed_at' => null,
                'email_failure_reason' => null,
            ])->save();

            return true;
        } catch (Throwable $exception) {
            $this->lastSendFailed = true;

            $notification->forceFill([
                'email_failed_at' => now(),
                'email_failure_reason' => str($exception->getMessage())->limit(1000)->toString(),
            ])->save();

            return false;
        }
    }

    /**
     * @return array{users_checked:int,emails_sent:int,skipped:int,failures:int}
     */
    private function sendDigests(string $period): array
    {
        $summary = $this->emailSummary();

        User::query()
            ->whereHas('notificationPreference', function ($query) use ($period): void {
                $query->where('email_notifications_enabled', true)
                    ->where($period === 'weekly' ? 'weekly_digest_email_enabled' : 'daily_digest_email_enabled', true);
            })
            ->get()
            ->each(function (User $user) use ($period, &$summary): void {
                $result = $this->sendDigestForUser($user, $period);

                $summary['users_checked'] += $result['users_checked'];
                $summary['emails_sent'] += $result['emails_sent'];
                $summary['skipped'] += $result['skipped'];
                $summary['failures'] += $result['failures'];
            });

        return $summary;
    }

    /**
     * @return Collection<int, SystemNotification>
     */
    private function digestNotifications(User $user): Collection
    {
        return SystemNotification::query()
            ->visibleTo($user)
            ->unread()
            ->active()
            ->orderByRaw("case priority when 'Critical' then 1 when 'High' then 2 when 'Normal' then 3 when 'Low' then 4 else 5 end")
            ->latest('generated_at')
            ->get();
    }

    /**
     * @return array{users_checked:int,emails_sent:int,skipped:int,failures:int}
     */
    private function emailSummary(): array
    {
        return [
            'users_checked' => 0,
            'emails_sent' => 0,
            'skipped' => 0,
            'failures' => 0,
        ];
    }

    /**
     * @param  array{users_checked:int,emails_sent:int,skipped:int,failures:int}  $summary
     */
    private function recordSendResult(array &$summary, bool $sent): void
    {
        if ($sent) {
            $summary['emails_sent']++;

            return;
        }

        if ($this->lastSendFailed) {
            $summary['failures']++;

            return;
        }

        $summary['skipped']++;
    }
}
