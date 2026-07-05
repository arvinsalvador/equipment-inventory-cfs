@php($vapidPublicKey = config('webpush.vapid.public_key'))

<div
    class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    data-browser-push-panel
    data-browser-push-public-key="{{ $vapidPublicKey }}"
    data-browser-push-configured="{{ filled($vapidPublicKey) ? '1' : '0' }}"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm font-semibold text-gray-950 dark:text-white">This device</div>
            <div class="mt-1 text-sm text-gray-600 dark:text-gray-400" data-browser-push-status>
                Checking browser notification support...
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                data-browser-push-enable
                @disabled(blank($vapidPublicKey))
            >
                Enable Browser Notifications
            </button>
            <button
                type="button"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                data-browser-push-disable
                disabled
            >
                Disable Browser Notifications
            </button>
        </div>
    </div>

    @if (blank($vapidPublicKey))
        <div class="mt-3 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950 dark:text-warning-200">
            Browser Push is disabled because VAPID keys are missing. Generate keys with <code>php artisan webpush:vapid --show</code>, then set <code>VAPID_PUBLIC_KEY</code>, <code>VAPID_PRIVATE_KEY</code>, and <code>VAPID_SUBJECT</code> in the environment.
        </div>
    @endif
</div>

@once
    <script src="{{ asset('browser-push.js') }}" defer></script>
@endonce
