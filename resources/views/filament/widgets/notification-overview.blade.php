@php
    use App\Filament\Resources\SystemNotifications\SystemNotificationResource;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Notification Alerts</x-slot>
        <x-slot name="description">Current in-app alerts for maintenance operations.</x-slot>

        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Unread Notifications</div>
                <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $this->unreadCount() }}</div>
            </div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                <div class="text-xs font-semibold uppercase text-red-700 dark:text-red-200">Critical Alerts</div>
                <div class="mt-2 text-2xl font-bold text-red-700 dark:text-red-200">{{ $this->criticalCount() }}</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
                <div class="text-xs font-semibold uppercase text-amber-700 dark:text-amber-200">High Priority</div>
                <div class="mt-2 text-2xl font-bold text-amber-700 dark:text-amber-200">{{ $this->highPriorityCount() }}</div>
            </div>
        </div>

        <div class="mt-5 space-y-3">
            @forelse ($this->latestNotifications() as $notification)
                <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">{{ $notification->title }}</div>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $notification->message }}</div>
                    </div>
                    <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ $notification->priority }}
                    </span>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    No active notifications.
                </div>
            @endforelse
        </div>

        <div class="mt-5">
            <a href="{{ SystemNotificationResource::getUrl('index') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                <x-filament::icon icon="heroicon-o-bell-alert" class="h-4 w-4" />
                View Notification Center
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
