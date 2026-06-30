<x-filament-widgets::widget>
    <section aria-labelledby="operations-kpis">
        <h2 id="operations-kpis" class="sr-only">Operations KPIs</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
            @foreach ($this->stats() as $stat)
                @php
                    $classes = match ($stat['color']) {
                        'blue' => 'border-blue-200 bg-blue-50 text-blue-700 ring-blue-100 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200 dark:ring-blue-900',
                        'orange' => 'border-orange-200 bg-orange-50 text-orange-700 ring-orange-100 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200 dark:ring-orange-900',
                        'green' => 'border-green-200 bg-green-50 text-green-700 ring-green-100 dark:border-green-900 dark:bg-green-950 dark:text-green-200 dark:ring-green-900',
                        'purple' => 'border-purple-200 bg-purple-50 text-purple-700 ring-purple-100 dark:border-purple-900 dark:bg-purple-950 dark:text-purple-200 dark:ring-purple-900',
                        'red' => 'border-red-200 bg-red-50 text-red-700 ring-red-100 dark:border-red-900 dark:bg-red-950 dark:text-red-200 dark:ring-red-900',
                        default => 'border-yellow-200 bg-yellow-50 text-yellow-700 ring-yellow-100 dark:border-yellow-900 dark:bg-yellow-950 dark:text-yellow-200 dark:ring-yellow-900',
                    };
                @endphp

                <article class="min-h-32 rounded-xl border bg-white p-4 shadow-sm ring-1 {{ $classes }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-5">{{ $stat['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold tracking-normal">{{ $stat['value'] }}</p>
                        </div>
                        <div class="shrink-0 rounded-lg bg-white/70 p-1.5 dark:bg-white/10">
                            <x-filament::icon :icon="$stat['icon']" class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs font-medium leading-5 opacity-80">{{ $stat['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
