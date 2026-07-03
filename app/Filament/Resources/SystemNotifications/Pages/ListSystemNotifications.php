<?php

namespace App\Filament\Resources\SystemNotifications\Pages;

use App\Filament\Resources\SystemNotifications\SystemNotificationResource;
use App\Services\SystemNotificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSystemNotifications extends ListRecords
{
    protected static string $resource = SystemNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllAsRead')
                ->label('Mark All as Read')
                ->icon('heroicon-o-check-circle')
                ->action(function (SystemNotificationService $service): void {
                    $count = $service->markAllAsRead(auth()->user());

                    Notification::make()
                        ->title("Marked {$count} notifications as read")
                        ->success()
                        ->send();
                }),
        ];
    }
}
