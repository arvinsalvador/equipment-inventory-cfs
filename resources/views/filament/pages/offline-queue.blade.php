<x-filament-panels::page>
    <div class="pwa-offline-workspace space-y-5">
        <x-filament::section>
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Offline synchronization</p>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">Offline Queue</h1>
                    <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                        Review local drafts, pending actions, failed synchronizations, and completed sync history for this browser.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="button" data-offline-sync-now icon="heroicon-o-arrow-path">Retry Sync</x-filament::button>
                    <x-filament::button tag="a" href="{{ url('/admin/mobile-technician-dashboard') }}" color="gray">Dashboard</x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-offline-banner hidden>
            <strong>Working Offline</strong>
            <span class="ms-1">Changes will be synchronized automatically.</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Pending actions</p>
                <p class="mt-2 text-2xl font-semibold" data-offline-pending-count>0</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Failed actions</p>
                <p class="mt-2 text-2xl font-semibold" data-offline-failed-count>0</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Successfully synchronized</p>
                <p class="mt-2 text-2xl font-semibold" data-offline-synced-count>0</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Drafts saved offline</p>
                <p class="mt-2 text-2xl font-semibold" data-offline-draft-count>0</p>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">Synchronization Status</x-slot>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Connection</p>
                    <p class="mt-1 text-sm font-semibold" data-pwa-online-status>Checking</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Sync status</p>
                    <p class="mt-1 text-sm font-semibold" data-offline-sync-status>Checking</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Last Synchronization</p>
                    <p class="mt-1 text-sm font-semibold" data-offline-last-sync>Never</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Pending actions</x-slot>
            <div class="space-y-3" data-offline-queue-list data-offline-filter="pending"></div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Failed actions</x-slot>
            <div class="space-y-3" data-offline-queue-list data-offline-filter="failed"></div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Successfully synchronized actions</x-slot>
            <div class="space-y-3" data-offline-queue-list data-offline-filter="synced"></div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Local drafts</x-slot>
            <div class="space-y-3" data-offline-draft-list></div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
