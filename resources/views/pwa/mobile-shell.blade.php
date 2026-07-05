@auth
    <div class="pwa-install-prompt" data-pwa-install-prompt hidden>
        <div>
            <strong>Install App</strong>
            <span>Open the maintenance workspace faster on this device.</span>
        </div>
        <div class="pwa-install-actions">
            <button type="button" data-pwa-install-button>Install App</button>
            <button type="button" data-pwa-install-dismiss aria-label="Dismiss install prompt">Later</button>
        </div>
    </div>

    <a class="pwa-qr-fab" href="{{ route('equipment.scan') }}" data-pwa-qr-link aria-label="Scan QR">
        <span>QR</span>
    </a>

    <nav class="pwa-bottom-nav" aria-label="Mobile technician navigation">
        <a href="{{ url('/admin/mobile-technician-dashboard') }}">Home</a>
        <a href="{{ url('/admin/equipment') }}">Equipment</a>
        <a href="{{ url('/admin/work-orders') }}">Work Orders</a>
        <a href="{{ route('equipment.scan') }}" data-pwa-qr-link>Scan QR</a>
        <a href="{{ url('/admin/system-notifications') }}">Notifications</a>
    </nav>
@endauth
