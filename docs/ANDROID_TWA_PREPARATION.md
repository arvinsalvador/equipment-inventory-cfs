# Android TWA Preparation

## Overview

Phase 15A prepares the existing PWA for future Android packaging. It does not generate an APK or AAB, create a native Android project, publish to the Play Store, or replace the Laravel, Filament, QR scanner, offline queue, notification, and rule-based recommendation architecture.

## Why Trusted Web Activity Is Recommended

Trusted Web Activity is the preferred first Android packaging path because the system is already a PWA. TWA lets the Android app launch the hosted web application in a trusted Chrome-powered container while preserving the shared-hosting deployment model, existing Laravel routes, Filament screens, service worker, QR lookup, and offline queue behavior.

## Server Requirements

- Production site served over HTTPS.
- Valid SSL certificate with no mixed-content errors.
- Laravel public directory configured as the document root.
- `/manifest.webmanifest` accessible publicly.
- `/service-worker.js` accessible publicly.
- `/offline` accessible publicly.
- Admin, Filament, Livewire, login, logout, and non-GET routes excluded from offline-shell fallback.
- Public storage link configured for equipment photos and generated QR code assets.

## HTTPS Requirement

Android camera access and PWA installability require HTTPS in production. Localhost is acceptable only for development testing. Before Android device validation, verify that the deployed domain uses HTTPS and that the QR scanner page does not load mixed HTTP assets.

## PWA Installability Requirement

The web app should provide:

- A valid manifest with name, short name, description, start URL, scope, standalone display mode, theme color, background color, orientation, icons, shortcuts, and categories.
- A service worker that safely handles public shell assets.
- A mobile-friendly start URL, currently `/admin/mobile-technician-dashboard?source=pwa`.
- A working offline fallback for safe public pages.
- No service worker interception of `/admin`, `/filament`, `/livewire`, `/login`, `/logout`, or mutating requests.

## Manifest Requirements

The manifest should remain focused on the existing PWA:

- `name`: AI Based Equipment Inventory and Maintenance
- `short_name`: AI Equipment
- `start_url`: mobile technician dashboard
- `scope`: `/`
- `display`: `standalone`
- `orientation`: `portrait`
- `theme_color` and `background_color`
- Icons including maskable purpose
- Android-friendly shortcuts for dashboard and QR scan
- Categories for business/productivity use

PNG launcher assets can be added later during APK/AAB generation if the packaging tool requires raster icons.

## Digital Asset Links Requirement

Trusted Web Activity requires Digital Asset Links to prove that the Android package and website are controlled by the same owner.

The final file must be served at:

```text
https://your-domain.example/.well-known/assetlinks.json
```

This repository includes a placeholder template:

```text
public/.well-known/assetlinks.template.json
```

Do not deploy the template as the real `assetlinks.json` until these values are replaced:

- Android package name
- Release signing certificate SHA-256 fingerprint

## Package Name Placeholder

Suggested package name placeholder:

```text
com.example.ai_equipment_inventory
```

Before Phase 15B, replace this with the organization-owned package name that will be used permanently for APK/AAB signing and Play Store publishing.

## SHA-256 Certificate Fingerprint Placeholder

The template contains:

```text
REPLACE_WITH_RELEASE_CERTIFICATE_SHA256_FINGERPRINT
```

This must be replaced with the real release signing certificate fingerprint generated during Android packaging.

## Bubblewrap Overview

Bubblewrap is a common tool for generating a Trusted Web Activity Android project from a PWA manifest. The expected future flow is:

1. Verify production HTTPS deployment.
2. Verify manifest and service worker installability.
3. Generate or choose the Android package name.
4. Generate release signing key.
5. Create real `assetlinks.json`.
6. Use Bubblewrap to initialize the TWA project from the manifest URL.
7. Build APK or AAB.
8. Test on real Android devices.

Phase 15A does not run Bubblewrap or create the Android project.

## APK/AAB Generation Checklist

- Production domain is final.
- HTTPS is active.
- Manifest loads from production.
- Service worker loads from production.
- Admin routes are not intercepted by offline fallback.
- Real package name is selected.
- Release signing key is created and stored securely.
- Real Digital Asset Links file is deployed.
- Launcher and adaptive icon assets are ready.
- Splash screen asset is ready.
- Play Store feature graphic and screenshots are ready.
- QR scanner, offline queue, login, and sync behavior are tested on Android.

## Android Asset Checklist

- Launcher icon.
- Adaptive icon foreground.
- Adaptive icon background.
- Splash screen asset.
- Maskable icon.
- Play Store feature graphic.
- Play Store phone screenshots.
- App name confirmation.
- Final package name.

Existing SVG PWA icons are suitable placeholders for readiness checks, but final Android store assets should be reviewed and exported at required Play Store dimensions during Phase 15B.

## Capacitor Alternative

| Approach | Fit | Notes |
| --- | --- | --- |
| TWA | Recommended first | Best match for an existing PWA on shared hosting. Keeps Laravel and Filament unchanged. |
| Capacitor | Possible later | Useful if native plugins are required, but adds native project maintenance. |
| WebView wrapper | Lowest preference | Simple but weaker integration, installability, and trust model than TWA. |

Use TWA first because the system is already a PWA and shared-hosting compatible.

## QR Scanner Android Compatibility Notes

- Camera access requires HTTPS in production.
- Chrome Android will prompt for camera permission.
- TWA camera permission behavior must be tested on real devices.
- The scanner should retain the manual QR lookup fallback.
- Recommended first test browser is Chrome Android.
- Test both direct scanner entry and QR lookup URL entry.

## Online-only Mobile Dashboard Android Notes

- Offline synchronization is intentionally deferred.
- The intended mobile entry point is the Technician Mobile Dashboard.
- The installed PWA should load live data while connected.
- Admin, Filament, and Livewire routes must remain network-first and must not be cached as app pages.
- QR scanning, uploads, notifications, and maintenance workflows should be tested on real Android devices.

## Testing Checklist

- Install prompt appears in supported Android Chrome.
- App opens to the mobile technician dashboard.
- Admin login works normally.
- QR scanner opens over HTTPS.
- Manual QR lookup fallback works.
- Offline page works for safe public navigation.
- Online technician dashboard loads live data.
- Offline synchronization remains deferred.
- Equipment photos and QR code images load from `/storage`.
- Service worker does not hijack `/admin`, `/filament`, or `/livewire`.

## Known Limitations

- APK/AAB generation is deferred to Phase 15B.
- Real Digital Asset Links cannot be completed until the package name and signing certificate are final.
- Play Store assets are not generated in Phase 15A.
- Native camera integration is not implemented.
- Native push notifications are not implemented.
- Real Android device testing is still required.
