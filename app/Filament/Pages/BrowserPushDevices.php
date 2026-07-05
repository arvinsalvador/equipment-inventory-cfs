<?php

namespace App\Filament\Pages;

use App\Models\BrowserPushSubscription;
use App\Models\User;
use App\Services\BrowserPushPreparationService;
use App\Services\BrowserPushService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BrowserPushDevices extends Page
{
    protected string $view = 'filament.pages.browser-push-devices';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|\UnitEnum|null $navigationGroup = 'System Management';

    protected static ?string $navigationLabel = 'Browser Push Devices';

    protected static ?int $navigationSort = 45;

    public ?int $test_user_id = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access admin panel') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Browser Push Devices';
    }

    /**
     * @return array{enabled:bool,active_subscriptions:int,ready:bool,status:string}
     */
    public function readiness(): array
    {
        return app(BrowserPushPreparationService::class)->getReadinessForUser(auth()->user());
    }

    public function subscriptions()
    {
        return auth()->user()
            ->browserPushSubscriptions()
            ->latest()
            ->get();
    }

    public function revoke(int $subscriptionId): void
    {
        $subscription = BrowserPushSubscription::query()
            ->forUser(auth()->user())
            ->findOrFail($subscriptionId);

        app(BrowserPushPreparationService::class)->revokeSubscription($subscription);

        Notification::make()
            ->title('Browser push subscription revoked')
            ->success()
            ->send();
    }

    public function sendTestToCurrentUser(): void
    {
        $this->notifyTestResult(app(BrowserPushService::class)->sendTestToUser(auth()->user()));
    }

    public function sendTestToSelectedUser(): void
    {
        abort_unless(auth()->user()?->hasRole('Administrator'), 403);

        $user = User::query()->findOrFail($this->test_user_id);

        $this->notifyTestResult(app(BrowserPushService::class)->sendTestToUser($user), $user->name);
    }

    public function usersForTest()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * @param  array{attempted:bool,sent:int,reason:?string}  $result
     */
    private function notifyTestResult(array $result, ?string $recipient = null): void
    {
        $title = $result['sent'] > 0
            ? 'Test browser notification sent'
            : 'Test browser notification not sent';

        $body = $result['sent'] > 0
            ? 'Delivered to '.$result['sent'].' active device'.($recipient ? " for {$recipient}" : '').'.'
            : ($result['reason'] ?? 'No active browser push device was available.');

        $notification = Notification::make()
            ->title($title)
            ->body($body);

        $result['sent'] > 0 ? $notification->success() : $notification->warning();

        $notification->send();
    }
}
