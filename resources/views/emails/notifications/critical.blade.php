<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Critical Alert</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h1 style="font-size: 22px; margin-bottom: 4px;">{{ $systemName }}</h1>
    <p style="margin-top: 0; color: #6b7280;">Immediate critical notification</p>

    <h2 style="font-size: 18px;">{{ $notification->title }}</h2>
    <p>{{ $notification->message }}</p>

    <table style="border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 4px 16px 4px 0; font-weight: bold;">Priority</td>
            <td>{{ $notification->priority }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 16px 4px 0; font-weight: bold;">Category</td>
            <td>{{ $notification->category }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 16px 4px 0; font-weight: bold;">Generated</td>
            <td>{{ $notification->generated_at?->format('M d, Y g:i A') }}</td>
        </tr>
    </table>

    @if ($notification->action_url)
        <p style="margin-top: 20px;">
            <a href="{{ $notification->action_url }}" style="display: inline-block; background: #2563eb; color: #ffffff; padding: 10px 14px; text-decoration: none; border-radius: 6px;">Open related record</a>
        </p>
    @endif
</body>
</html>
