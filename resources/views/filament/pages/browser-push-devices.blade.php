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
                    <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Delivery status</div>
                    <div class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $readiness['status'] }}</div>
                </div>
            </div>
        </x-filament::section>

        @include('pwa.browser-push-controls')

        <x-filament::section>
            <x-slot name="heading">Send Test Browser Notification</x-slot>

            <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                <label class="block text-sm font-medium text-gray-900 dark:text-white">
                    <span>Selected user</span>
                    <select wire:model="test_user_id" class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Choose a user</option>
                        @foreach ($this->usersForTest() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex flex-wrap gap-2">
                    <button wire:click="sendTestToCurrentUser" type="button" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                        Send to Current User
                    </button>
                    @if (auth()->user()?->hasRole('Administrator'))
                        <button wire:click="sendTestToSelectedUser" type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" @disabled(blank($test_user_id))>
                            Send to Selected User
                        </button>
                    @endif
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
                            <th class="py-3 pe-4 font-semibold">Browser</th>
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
                                <td class="py-4 pe-4 text-gray-600 dark:text-gray-400">{{ trim(($subscription->browser ?: 'Unknown').' '.($subscription->platform ? 'on '.$subscription->platform : '')) }}</td>
                                <td class="py-4 pe-4 text-gray-600 dark:text-gray-400">{{ str($subscription->user_agent ?: 'Unknown')->limit(80) }}</td>
                                <td class="py-4 pe-4">{{ $subscription->isActive() ? 'Active' : 'Revoked' }}</td>
                                <td class="py-4 pe-4 text-gray-600 dark:text-gray-400">{{ ($subscription->last_used_at ?? $subscription->last_seen_at)?->format('M d, Y g:i A') ?: 'Never' }}</td>
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
