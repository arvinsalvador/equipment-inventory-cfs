<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="px-3 py-4 text-sm text-gray-500" colspan="{{ count($columns) }}">{{ $empty }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
