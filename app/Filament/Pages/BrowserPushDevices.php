<?php

namespace App\Filament\Pages;

use App\Models\BrowserPushSubscription;
use App\Services\BrowserPushPreparationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BrowserPushDevices extends Page
{
    protected string $view = 'filament.pages.browser-push-devices';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|\UnitEnum|null $navigationGroup = 'System Management';

    protected static ?string $navigationLabel = 'Browser Push Devices';

    protected static ?int $navigationSort = 45;

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
}
