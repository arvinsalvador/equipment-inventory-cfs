<x-filament-panels::page>
    <form wire:submit="save" class="max-w-3xl space-y-6">
        <x-filament::section>
            <x-slot name="heading">In-app alert categories</x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    'maintenance_reminders' => 'Maintenance reminders',
                    'work_order_alerts' => 'Work order alerts',
                    'maintenance_request_alerts' => 'Maintenance request alerts',
                    'ai_recommendation_alerts' => 'AI recommendation alerts',
                    'lifecycle_alerts' => 'Lifecycle alerts',
                    'warranty_alerts' => 'Warranty alerts',
                    'evidence_alerts' => 'Evidence alerts',
                    'system_alerts' => 'System alerts',
                ] as $field => $label)
                    <label class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 text-sm font-medium text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <span>{{ $label }}</span>
                        <input type="checkbox" wire:model="{{ $field }}" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                    </label>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Email delivery</x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    'email_notifications_enabled' => 'Enable email notifications',
                    'immediate_critical_email_enabled' => 'Send immediate emails for critical alerts',
                    'daily_digest_email_enabled' => 'Send daily digest email',
                    'weekly_digest_email_enabled' => 'Send weekly digest email',
                ] as $field => $label)
                    <label class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 text-sm font-medium text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <span>{{ $label }}</span>
                        <input type="checkbox" wire:model="{{ $field }}" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                    </label>
                @endforeach
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="block text-sm font-medium text-gray-900 dark:text-white">
                    <span>Preferred digest time</span>
                    <input type="time" wire:model="digest_time" class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    @error('digest_time')
                        <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block text-sm font-medium text-gray-900 dark:text-white">
                    <span>Preferred weekly digest day</span>
                    <select wire:model="digest_day_of_week" class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">No preference</option>
                        @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                            <option value="{{ $day }}">{{ $day }}</option>
                        @endforeach
                    </select>
                    @error('digest_day_of_week')
                        <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>
                    @enderror
                </label>
            </div>
        </x-filament::section>

        <x-filament::button type="submit" icon="heroicon-o-check">
            Save Preferences
        </x-filament::button>
    </form>
</x-filament-panels::page>
