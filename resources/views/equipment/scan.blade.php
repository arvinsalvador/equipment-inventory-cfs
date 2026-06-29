<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan Equipment</title>
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f8fafc; color: #111827; }
        main { max-width: 780px; margin: 0 auto; padding: 24px; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        video { width: 100%; max-height: 420px; background: #020617; border-radius: 8px; }
        button, input { font: inherit; }
        button { background: #111827; color: #fff; border: 0; padding: 10px 14px; border-radius: 6px; cursor: pointer; }
        input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; }
        form { display: grid; gap: 10px; margin-top: 18px; }
        .error { color: #b91c1c; font-weight: 600; min-height: 24px; }
        .hint { color: #475569; }
        @media (max-width: 700px) { main { padding: 16px; } }
    </style>
</head>
<body>
<main>
    <section class="panel">
        <h1>Scan Equipment</h1>
        <p class="hint">Allow camera access and point the rear camera at an equipment QR code. You can also enter a QR identifier manually.</p>
        <video id="scanner-preview" playsinline muted></video>
        <p id="scanner-message" class="error"></p>
        <button id="start-scanner" type="button">Start camera scanner</button>

        <form method="POST" action="{{ route('equipment.scan.manual') }}">
            @csrf
            <label for="qr_identifier">Manual QR identifier or lookup URL</label>
            <input id="qr_identifier" name="qr_identifier" autocomplete="off" required>
            <button type="submit">Open equipment</button>
        </form>
    </section>
</main>
<script>
(() => {
    const startButton = document.getElementById('start-scanner');
    const video = document.getElementById('scanner-preview');
    const message = document.getElementById('scanner-message');
    let stream;
    let detector;

    function redirectFromValue(rawValue) {
        const value = rawValue.trim();

        if (!value) {
            message.textContent = 'The scanned QR code did not contain a value.';
            return;
        }

        try {
            const url = new URL(value, window.location.origin);
            if (url.pathname.startsWith('/equipment/lookup/')) {
                window.location.href = url.pathname;
                return;
            }
        } catch (error) {}

        if (/^[A-Za-z0-9._:-]+$/.test(value)) {
            window.location.href = '{{ url('/equipment/lookup') }}/' + encodeURIComponent(value);
            return;
        }

        message.textContent = 'The scanned QR code is not a valid equipment code.';
    }

    async function scanFrame() {
        if (!detector || !stream) return;

        try {
            const codes = await detector.detect(video);
            if (codes.length > 0) {
                stream.getTracks().forEach(track => track.stop());
                redirectFromValue(codes[0].rawValue || '');
                return;
            }
        } catch (error) {
            message.textContent = 'Unable to read from the camera. Try manual entry.';
            return;
        }

        window.requestAnimationFrame(scanFrame);
    }

    startButton.addEventListener('click', async () => {
        message.textContent = '';

        if (!('BarcodeDetector' in window)) {
            message.textContent = 'Camera QR scanning is not supported by this browser. Use manual entry below.';
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            message.textContent = 'Camera access is not available in this browser. Use manual entry below.';
            return;
        }

        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = stream;
            await video.play();
            window.requestAnimationFrame(scanFrame);
        } catch (error) {
            message.textContent = 'Camera access failed. Check browser permissions or use manual entry.';
        }
    });
})();
</script>
</body>
</html>
