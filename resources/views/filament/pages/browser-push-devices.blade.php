<x-filament-panels::page>
    @php($readiness = $this->readiness())

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Browser push readiness</x-slot>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Browser push enabled</div>
                    <div class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $readiness['enabled'] ? 'Yes' : 'No' }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Active subscriptions</div>
                    <div class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $readiness['active_subscriptions'] }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Future delivery status</div>
                    <div class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $readiness['status'] }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Browser push devices</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="py-3 pe-4 font-semibold">Device</th>
                            <th class="py-3 pe-4 font-semibold">User agent</th>
                            <th class="py-3 pe-4 font-semibold">Status</th>
                            <th class="py-3 pe-4 font-semibold">Last seen</th>
                            <th class="py-3 pe-4 font-semibold">Revoked</th>
                            <th class="py-3 font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->subscriptions() as $subscription)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pe-4 font-semibold text-gray-950 dark:text-white">{{ $subscription->device_name ?: 'Unnamed browser' }}</td>
                                <td class="py-4 pe-4 text-gray-600 dark:text-gray-400">{{ str($subscription->user_agent ?: 'Unknown')->limit(80) }}</td>
                                <td class="py-4 pe-4">{{ $subscription->isActive() ? 'Active' : 'Revoked' }}</td>
                                <td class="py-4 pe-4 text-gray-600 dark:text-gray-400">{{ $subscription->last_seen_at?->format('M d, Y g:i A') ?: 'Never' }}</td>
                                <td class="py-4 pe-4 text-gray-600 dark:text-gray-400">{{ $subscription->revoked_at?->format('M d, Y g:i A') ?: 'No' }}</td>
                                <td class="py-4">
                                    @if ($subscription->isActive())
                                        <button wire:click="revoke({{ $subscription->id }})" type="button" class="rounded-lg bg-danger-600 px-3 py-2 text-xs font-semibold text-white hover:bg-danger-500">
                                            Revoke
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No browser push subscriptions are registered yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
