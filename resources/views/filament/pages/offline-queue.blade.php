<x-filament-panels::page>
    <div class="pwa-offline-workspace space-y-5" data-offline-sync-surface="offline-queue">
        <x-filament::section>
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Offline synchronization</p>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">Offline Queue</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Review pending browser-stored actions, failed syncs, synchronized history, and local drafts before returning to the field.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="button" data-offline-sync-now icon="heroicon-o-arrow-path">Sync Now</x-filament::button>
                    <x-filament::button type="button" data-offline-sync-now color="gray" icon="heroicon-o-arrow-uturn-left">Retry Failed</x-filament::button>
                    <x-filament::button tag="a" href="{{ url('/admin/mobile-technician-dashboard') }}" color="gray" icon="heroicon-o-device-phone-mobile">Technician Dashboard</x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100" data-offline-banner hidden>
            <strong>Working offline.</strong>
            <span class="ms-1">Changes will sync when connection returns.</span>
        </div>

        <x-filament::section>
            <x-slot name="heading">Synchronization Summary</x-slot>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Connection Status</p>
                    <p class="mt-2 text-lg font-semibold text-gray-950 dark:text-white" data-pwa-online-status>Checking</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Total Pending Offline Actions</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-pending-count>0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Failed Sync Count</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-failed-count>0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Successfully Synced Count</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-synced-count>0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Last Sync Attempt</p>
                    <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white" data-offline-last-sync>Never</p>
                </div>
            </div>
            <p class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200" data-offline-sync-message>
                All offline changes have been synchronized. No pending actions.
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Queue Items by Type</x-slot>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Work Orders</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-type-count="work_orders">0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Maintenance Requests</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-type-count="maintenance_requests">0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Evidence Uploads</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-type-count="evidence_uploads">0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Equipment Updates</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-type-count="equipment_updates">0</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Other Offline Actions</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" data-offline-type-count="other">0</p>
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Pending Queue</x-slot>
                <div class="space-y-3" data-offline-queue-list data-offline-filter="pending"></div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Failed Syncs</x-slot>
                <div class="space-y-3" data-offline-queue-list data-offline-filter="failed"></div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Successfully Synchronized</x-slot>
                <div class="space-y-3" data-offline-queue-list data-offline-filter="synced"></div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Local Drafts</x-slot>
                <div class="space-y-3" data-offline-draft-list></div>
            </x-filament::section>
        </div>
    </div>

    <script src="{{ asset('offline-sync.js') }}" defer></script>
</x-filament-panels::page>
