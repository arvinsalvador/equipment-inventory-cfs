<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Quick Actions</x-slot>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach ($this->actions() as $action)
                @php
                    $classes = match ($action['color']) {
                        'blue' => 'border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-200 dark:hover:bg-blue-950',
                        'orange' => 'border-orange-200 text-orange-700 hover:bg-orange-50 dark:border-orange-900 dark:text-orange-200 dark:hover:bg-orange-950',
                        'green' => 'border-green-200 text-green-700 hover:bg-green-50 dark:border-green-900 dark:text-green-200 dark:hover:bg-green-950',
                        'red' => 'border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-200 dark:hover:bg-red-950',
                        'purple' => 'border-purple-200 text-purple-700 hover:bg-purple-50 dark:border-purple-900 dark:text-purple-200 dark:hover:bg-purple-950',
                        default => 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900',
                    };
                @endphp

                <a href="{{ $action['url'] }}" class="flex min-h-16 items-center gap-2 rounded-xl border bg-white p-3 text-xs font-semibold leading-5 shadow-sm transition dark:bg-gray-900 {{ $classes }}">
                    <x-filament::icon :icon="$action['icon']" class="h-4 w-4 shrink-0" />
                    <span class="min-w-0">{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
