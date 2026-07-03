<x-filament-panels::page>
    @php($overview = $this->technicianOverview())

    <div class="pwa-technician-dashboard space-y-5">
        <x-filament::section>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Mobile technician workspace</p>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">Today at a glance</h1>
                </div>
                <x-filament::button tag="a" href="{{ route('equipment.scan') }}" icon="heroicon-o-qr-code">
                    Scan QR
                </x-filament::button>
            </div>
        </x-filament::section>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Assigned Work Orders</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['assigned_work_orders_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Due Today</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['due_today_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Notifications</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['unread_notifications_count'] }}</p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Pending Offline Actions</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white" data-offline-pending-count>0</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Last Synchronization</p>
                <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white" data-offline-last-sync>Never</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Drafts Saved Offline</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white" data-offline-draft-count>0</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Failed Synchronizations</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white" data-offline-failed-count>0</p>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">Assigned Work Orders</x-slot>
            <div class="space-y-3">
                @forelse ($overview['assigned_work_orders'] as $workOrder)
                    <a href="{{ url('/admin/work-orders/'.$workOrder->id) }}" class="block rounded-lg border border-gray-200 p-4 text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-950 dark:text-white">{{ $workOrder->work_order_number }}</p>
                                <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $workOrder->title }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $workOrder->equipment?->equipment_name ?: 'No equipment linked' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20">{{ $workOrder->status }}</span>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No assigned work orders are waiting for action.</p>
                @endforelse
            </div>
        </x-filament::section>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Due Today</x-slot>
                <div class="space-y-3">
                    @forelse ($overview['due_today'] as $schedule)
                        <div class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-800">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $schedule->maintenance_type }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $schedule->equipment?->equipment_name ?: 'No equipment linked' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $schedule->priority }} priority</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No preventive maintenance schedules are due today.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Notifications</x-slot>
                <div class="space-y-3">
                    @forelse ($overview['notifications'] as $notification)
                        <div class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-800">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $notification->title }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ str($notification->message)->limit(120) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No unread notifications.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Recent Equipment</x-slot>
            <div class="grid gap-3 sm:grid-cols-2">
                @forelse ($overview['recent_equipment'] as $equipment)
                    <a href="{{ url('/admin/equipment/'.$equipment->id) }}" class="rounded-lg border border-gray-200 p-4 text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900">
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $equipment->equipment_code }}</p>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $equipment->equipment_name }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $equipment->currentLocation?->name ?: 'No location' }}</p>
                    </a>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No recent equipment records are available.</p>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Device Information</x-slot>
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Online/Offline</p>
                    <p class="mt-1 text-sm font-semibold" data-pwa-online-status>Checking</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Sync status</p>
                    <p class="mt-1 text-sm font-semibold" data-offline-sync-status>Checking</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Browser</p>
                    <p class="mt-1 text-sm font-semibold" data-pwa-browser>Checking</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Install status</p>
                    <p class="mt-1 text-sm font-semibold" data-pwa-install-status>Checking</p>
                </div>
            </div>
        </x-filament::section>

        @include('pwa.offline-workspace')
    </div>
</x-filament-panels::page>
