<x-filament-panels::page>
    @php
        $overview = $this->technicianOverview();
        $hasTechnicianTasks = collect([
            $overview['assigned_work_orders_count'],
            $overview['open_work_orders_count'],
            $overview['overdue_work_orders_count'],
            $overview['due_soon_work_orders_count'],
            $overview['due_today_count'],
            $overview['maintenance_requests_needing_action_count'],
            $overview['evidence_required_count'],
            $overview['beyond_repair_count'],
            $overview['critical_recommendations_count'],
        ])->sum() > 0;
    @endphp

    <div class="pwa-technician-dashboard space-y-5">
        <x-filament::section>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Mobile technician workspace</p>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">Technician Mobile Dashboard</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Assigned work, maintenance requests, evidence needs, QR lookup, and offline synchronization for field technicians.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button tag="a" href="{{ route('equipment.scan') }}" icon="heroicon-o-qr-code">Scan QR Code</x-filament::button>
                    <x-filament::button tag="a" href="{{ url('/admin/work-orders') }}" color="gray" icon="heroicon-o-clipboard-document-list">View Assigned Work Orders</x-filament::button>
                    <x-filament::button tag="a" href="{{ url('/admin/offline-queue') }}" color="gray" icon="heroicon-o-circle-stack">View Offline Queue</x-filament::button>
                    @if ($overview['can_create_maintenance_request'])
                        <x-filament::button tag="a" href="{{ url('/admin/maintenance-requests/create') }}" color="gray" icon="heroicon-o-plus-circle">Create Maintenance Request</x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Assigned Work Orders</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['assigned_work_orders_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Open Work Orders</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['open_work_orders_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Overdue Work Orders</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['overdue_work_orders_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Due Soon</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['due_soon_work_orders_count'] + $overview['due_today_count'] + $overview['due_soon_schedules_count'] }}</p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Maintenance Requests Needing Action</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['maintenance_requests_needing_action_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Evidence Required</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['evidence_required_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Beyond-repair Verification</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['beyond_repair_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Critical Recommendations</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['critical_recommendations_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Notifications</p>
                <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $overview['unread_notifications_count'] }}</p>
            </div>
        </div>

        @unless ($hasTechnicianTasks)
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                All assigned technician tasks are currently up to date.
            </div>
        @endunless

        <x-filament::section>
            <x-slot name="heading">Offline Status</x-slot>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Connection</p>
                    <p class="mt-1 text-sm font-semibold" data-pwa-online-status>Checking</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Pending Offline Sync</p>
                    <p class="mt-1 text-sm font-semibold" data-offline-pending-count>0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Failed Sync</p>
                    <p class="mt-1 text-sm font-semibold" data-offline-failed-count>0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Last Sync</p>
                    <p class="mt-1 text-sm font-semibold" data-offline-last-sync>Never</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Status</p>
                    <p class="mt-1 text-sm font-semibold" data-offline-sync-status>Checking</p>
                </div>
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400" data-offline-sync-message>All offline changes have been synchronized. No pending actions.</p>
            <div class="mt-3">
                <x-filament::button type="button" data-offline-sync-now icon="heroicon-o-arrow-path" color="gray">Sync Now</x-filament::button>
            </div>
        </x-filament::section>

        <div class="grid gap-5 xl:grid-cols-2">
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

            <x-filament::section>
                <x-slot name="heading">Due Today or Due Soon</x-slot>
                <div class="space-y-3">
                    @forelse ($overview['due_soon_work_orders'] as $workOrder)
                        <a href="{{ url('/admin/work-orders/'.$workOrder->id) }}" class="block rounded-lg border border-gray-200 p-4 text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $workOrder->work_order_number }} - {{ $workOrder->title }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $workOrder->equipment?->equipment_name ?: 'No equipment linked' }}</p>
                            <p class="mt-1 text-xs text-gray-500">Due {{ optional($workOrder->due_date)->format('M d, Y') ?: 'date not set' }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No work orders are due today or due soon.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Maintenance Requests Needing Action</x-slot>
                <div class="space-y-3">
                    @forelse ($overview['maintenance_requests_needing_action'] as $request)
                        <a href="{{ url('/admin/maintenance-requests/'.$request->id) }}" class="block rounded-lg border border-gray-200 p-4 text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $request->request_number }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ str($request->problem_description)->limit(120) }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $request->status }} - {{ $request->severity }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No maintenance requests need action right now.</p>
                    @endforelse
                </div>
                <div class="mt-3">
                    <x-filament::button tag="a" href="{{ url('/admin/maintenance-requests') }}" color="gray" size="sm">View Maintenance Requests</x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Evidence Required Items</x-slot>
                <div class="space-y-3">
                    @forelse ($overview['evidence_required'] as $workOrder)
                        <a href="{{ url('/admin/work-orders/'.$workOrder->id) }}" class="block rounded-lg border border-gray-200 p-4 text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $workOrder->work_order_number }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $workOrder->title }}</p>
                            <p class="mt-1 text-xs text-gray-500">After-maintenance evidence is still required.</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No work orders are currently missing required evidence.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Recently Completed Work Orders</x-slot>
                <div class="space-y-3">
                    @forelse ($overview['recent_completed_work_orders'] as $workOrder)
                        <a href="{{ url('/admin/work-orders/'.$workOrder->id) }}" class="block rounded-lg border border-gray-200 p-4 text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $workOrder->work_order_number }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $workOrder->title }}</p>
                            <p class="mt-1 text-xs text-gray-500">Completed {{ optional($workOrder->completed_at)->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recently completed work orders.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Critical Recommendations</x-slot>
                <div class="space-y-3">
                    @forelse ($overview['critical_recommendations'] as $recommendation)
                        <div class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-800">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $recommendation->title }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $recommendation->equipment?->equipment_name ?: 'No equipment linked' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $recommendation->risk_level }} risk - {{ $recommendation->suggestedAction() }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No critical recommendations need immediate field action.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        @include('pwa.offline-workspace')
    </div>

    <script src="{{ asset('offline-sync.js') }}" defer></script>
</x-filament-panels::page>
