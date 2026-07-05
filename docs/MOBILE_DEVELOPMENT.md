# Mobile Development Over LAN

This guide is for local development only. It explains how to open the Laravel Sail application from a phone or tablet on the same Wi-Fi network without changing production deployment settings.

## Finding Local IP

Use the local network IP address of the development computer. Do not use `localhost` from the mobile device because that points to the phone itself.

Windows:

```bash
ipconfig
```

Look for the IPv4 address on the active Wi-Fi or Ethernet adapter.

Linux:

```bash
ip addr
```

Look for the `inet` address on the active network interface.

Mac:

```bash
ifconfig
```

Look for the `inet` address on the active Wi-Fi or Ethernet interface.

## Running Laravel Sail

Start the Sail containers:

```bash
./vendor/bin/sail up -d
```

The development compose file publishes the web container as:

```yaml
ports:
  - "${APP_PORT:-8087}:80"
```

For LAN testing, use a local `.env` value like:

```env
APP_URL=http://<LOCAL_IP>:8087
APP_PORT=8087
```

Do not commit a personal IP address.

## Running Vite

The Vite development server is configured to listen on all local interfaces:

```env
VITE_DEV_SERVER_HOST=0.0.0.0
```

Start Vite with:

```bash
npm run dev -- --host
```

If hot module reload does not connect from the mobile device, set the HMR host in your local `.env`:

```env
VITE_HMR_HOST=<LOCAL_IP>
VITE_HMR_CLIENT_PORT=5173
```

## Opening on Mobile

Connect the phone and development computer to the same Wi-Fi network, then open:

```text
http://<LOCAL_IP>:8087
```

Use the computer's LAN IP address, not `localhost`.

## Camera Permissions

The QR scanner depends on browser camera APIs. The phone and computer must be on the same Wi-Fi network and the browser must allow camera access.

Some browsers require a secure context for camera access. `localhost` is treated as secure on the development computer, but a LAN IP such as `http://<LOCAL_IP>:8087` may not be treated as secure on a phone.

Chrome on Android usually requires HTTPS for camera access on LAN IP addresses. The page may load correctly while camera scanning remains unavailable.

Safari on iOS also expects a secure context for camera capture and may block camera access on plain HTTP LAN addresses.

## QR Scanner Development Notes

The Camera API and Barcode Detection API are browser-dependent. Localhost works differently from a LAN IP because browsers grant special secure-context treatment to localhost only.

For realistic QR scanner testing, expose the local development site through HTTPS. Android Chrome has the best support for web QR scanning when HTTPS and camera permissions are available. iPhone Safari support can vary by iOS version and browser security policy, so manual lookup should be tested as a fallback.

## PWA Validation

After changing local network settings, verify:

- `/manifest.webmanifest` loads.
- `/service-worker.js` loads.
- `/offline` loads.
- The mobile technician dashboard loads.
- The offline queue loads.
- Admin pages are not replaced by offline status UI.

The service worker cache can hold older development files. Clear the browser's site data or unregister the service worker when testing changes.

If an installed development PWA still opens to stale offline status text such as `Synchronized`, uninstall the PWA, clear site data for the development origin, reopen the site in the browser, then reinstall the PWA.

## Troubleshooting

Windows Firewall may block inbound traffic to port `8087` or `5173`. Allow Docker Desktop, WSL, Node, or the relevant terminal through the firewall for private networks.

Confirm Docker is publishing ports on all interfaces. The compose port mapping should not use `127.0.0.1:8087:80`.

If uploaded photos or QR images do not load, run:

```bash
./vendor/bin/sail artisan storage:link
```

If mobile pages show stale content, clear browser cache and service worker cache for the local development origin.

If Vite assets do not refresh, stop and restart Vite, then clear the Vite/browser cache.

## Recommended Production Testing

HTTPS is recommended for production-equivalent testing, especially for QR scanning and install prompts. Useful development tunnels include:

- Cloudflare Tunnel
- Ngrok
- LocalTunnel

These tools provide an HTTPS URL that can be opened on a mobile device while still running the Laravel Sail application locally.
