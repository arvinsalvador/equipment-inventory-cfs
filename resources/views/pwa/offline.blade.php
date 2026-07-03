<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline Mode</title>
    @include('pwa.meta')
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #111827; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        main { width: min(92vw, 520px); border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; padding: 28px; box-shadow: 0 18px 45px rgba(15, 23, 42, .08); }
        h1 { margin: 0 0 10px; font-size: 28px; line-height: 1.15; }
        p { margin: 8px 0 0; color: #475569; line-height: 1.6; }
    </style>
</head>
<body>
    <main>
        <h1>Offline Mode</h1>
        <p>Internet connection unavailable.</p>
        <p>Some features require reconnecting.</p>
    </main>
</body>
</html>
