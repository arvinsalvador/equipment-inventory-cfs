<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Budget Planning Summary</x-slot>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($this->metrics() as $label => $value)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700 shadow-sm dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                    <div class="text-sm font-medium">{{ $label }}</div>
                    <div class="mt-3 text-2xl font-semibold tracking-normal">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
