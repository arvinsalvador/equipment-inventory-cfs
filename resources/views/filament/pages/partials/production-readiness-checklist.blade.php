<div class="space-y-3">
    @foreach ($items as $item)
        <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $item['description'] }}</p>
                </div>
                <span class="inline-flex w-fit rounded-md px-2 py-1 text-xs font-semibold uppercase ring-1 {{ $statusClasses[$item['status']] ?? $statusClasses['review'] }}">
                    {{ $item['status'] }}
                </span>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item['action'] }}</p>
        </div>
    @endforeach
</div>
