<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Notification Digest</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h1 style="font-size: 22px; margin-bottom: 4px;">{{ $systemName }}</h1>
    <p style="margin-top: 0; color: #6b7280;">{{ str($period)->title() }} notification digest for {{ $recipient->name }}</p>

    @forelse ($groupedNotifications as $priority => $items)
        <h2 style="font-size: 16px; margin-top: 22px;">{{ $priority }} Priority</h2>

        @foreach ($items as $notification)
            <div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 10px;">
                <strong>{{ $notification->title }}</strong>
                <p style="margin: 6px 0;">{{ $notification->message }}</p>
                <p style="margin: 0; color: #6b7280; font-size: 13px;">
                    {{ $notification->category }} &middot; {{ $notification->generated_at?->format('M d, Y g:i A') }}
                </p>
                @if ($notification->action_url)
                    <p style="margin: 8px 0 0;">
                        <a href="{{ $notification->action_url }}">Open related record</a>
                    </p>
                @endif
            </div>
        @endforeach
    @empty
        <p>No unread notifications are currently available.</p>
    @endforelse

    <p style="margin-top: 24px; color: #6b7280; font-size: 13px;">
        This digest was generated automatically by the system. Notifications are not marked as read by email delivery.
    </p>
</body>
</html>
