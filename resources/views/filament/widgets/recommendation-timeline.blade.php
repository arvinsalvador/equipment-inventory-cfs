<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recommendation Timeline</x-slot>

        <div class="space-y-5">
            @forelse ($this->records() as $record)
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $record->equipment->equipment_code }} - {{ $record->title }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $record->getSuggestedActionLabel() }}</div>
                        </div>
                        <span class="inline-flex w-fit rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">
                            {{ $record->status }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-2 text-xs sm:grid-cols-5">
                        @foreach ([
                            'Generated' => $record->generated_at,
                            'Reviewed' => $record->reviewed_at,
                            'Approved' => $record->action_status === 'Approved' || $record->action_status === 'Executed' ? $record->actioned_at : null,
                            'Executed' => $record->action_status === 'Executed' ? $record->actioned_at : null,
                            'Resolved' => $record->resolved_at,
                        ] as $label => $date)
                            <div class="rounded-md border border-gray-200 p-2 dark:border-gray-800">
                                <div class="font-semibold text-gray-700 dark:text-gray-300">{{ $label }}</div>
                                <div class="mt-1 text-gray-500 dark:text-gray-400">{{ $date?->format('M d') ?? 'Pending' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    No recommendation timeline entries yet.
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
