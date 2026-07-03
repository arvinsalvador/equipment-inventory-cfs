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

        <x-filament::button type="submit" icon="heroicon-o-check">
            Save Preferences
        </x-filament::button>
    </form>
</x-filament-panels::page>
