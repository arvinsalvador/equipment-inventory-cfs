# Browser Push Notifications

SEIMS browser push notifications add a web push channel to the existing database, Filament, and email notification system. The application remains an online-only PWA: there is no Offline Queue, Offline Sync, Background Sync, draft replay, or HTML navigation interception.

## Architecture

- Authenticated users enable push from Notification Preferences or Browser Push Devices.
- `browser-push.js` registers `/service-worker.js`, requests notification permission, subscribes through PushManager, and posts the subscription to `/push-subscriptions`.
- Subscriptions are stored in `browser_push_subscriptions` and support multiple devices per user.
- `SystemNotificationService` still creates the normal SEIMS notification first, then `BrowserPushService` attempts browser push when VAPID keys, user preferences, and active subscriptions are present.
- The service worker handles only `push`, `notificationclick`, app shell caching, and static asset caching. HTML remains network-first.

## Supported Browsers

- Chrome desktop
- Microsoft Edge desktop
- Chrome Android
- Installed Android PWA

Unsupported browsers display a status message and do not throw JavaScript errors. iOS Safari push behavior depends on installed web app support and production HTTPS.

## VAPID Setup

Browser push requires VAPID keys from environment variables:

```env
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:admin@seims.site
```

Generate keys without writing them to `.env`:

```bash
php artisan webpush:vapid --show
```

Copy the generated public and private keys into the production environment. Never commit or hardcode the private key.

If keys are missing, registration is disabled, administrators see a warning, and existing notification channels continue to work.

## Device Registration

The browser flow is:

1. User clicks `Enable Browser Notifications`.
2. Browser requests notification permission.
3. Service worker is registered.
4. PushManager subscribes using the configured VAPID public key.
5. The subscription is posted to `/push-subscriptions` as JSON.
6. Duplicate endpoints update the existing device record.

Users can disable the current device with `Disable Browser Notifications`, which revokes the stored subscription and unsubscribes the browser.

## Notification Flow

Browser Push respects the user Notification Preferences categories:

- Maintenance alerts
- Work order alerts
- Maintenance request alerts
- AI recommendation alerts
- Lifecycle alerts
- Warranty alerts
- Evidence alerts
- Budget notifications
- Asset action requests
- Executive notifications
- Critical/system alerts

Delivery is best effort. Invalid or expired endpoints are cleaned up by the web push channel report handler where supported.

## Administrator Test Notification

Administrators can open Browser Push Devices and send:

- A test notification to the current user.
- A test notification to a selected user.

The test notification opens `/admin/mobile-technician-dashboard` when clicked.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
php artisan webpush:vapid --show
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Verify HTTPS, confirm notification permission in the browser, register a device, and send a test browser notification.

## Bluehost Notes

- Configure VAPID keys in Bluehost environment settings or the Laravel `.env` file.
- Run migrations after deployment.
- Clear and rebuild Laravel config cache after changing VAPID values.
- Ensure HTTPS is active before testing browser push.

## Troubleshooting

- `VAPID keys missing`: set `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, and `VAPID_SUBJECT`, then clear config cache.
- `Permission Denied`: re-enable notifications in browser site settings.
- `Not Supported`: use Chrome, Edge, or Android Chrome.
- No notification appears: verify HTTPS, active subscription, user category preferences, and service worker registration.
- Installed PWA does not receive push: reinstall or refresh the PWA after deployment if the old service worker is still cached.
