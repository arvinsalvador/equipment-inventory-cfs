# Android Packaging With Trusted Web Activity

## Phase 15B Overview

Phase 15B prepares the project for Android APK and AAB packaging using a Trusted Web Activity strategy. This phase documents the Android packaging path and adds readiness checks, but it does not generate an APK, generate an AAB, publish to the Play Store, add native Android features, or replace the existing Laravel PWA.

## Why TWA Is Used

Trusted Web Activity is the preferred packaging approach because the application is already a mobile-ready PWA with a manifest, service worker, offline page, QR scanner, offline queue, and Filament-backed authenticated screens. TWA keeps the Laravel deployment model intact while allowing the installed Android app to open the production PWA in a trusted Chrome-powered container.

## Prerequisites

- Production Laravel deployment available on a stable HTTPS domain.
- PWA manifest available at `https://your-production-domain.example/manifest.webmanifest`.
- Service worker available at `https://your-production-domain.example/service-worker.js`.
- Offline page available at `https://your-production-domain.example/offline`.
- Admin, Filament, Livewire, login, logout, and non-GET requests excluded from service worker offline fallback.
- Android package name selected.
- Android app signing key generated and stored securely.
- SHA-256 signing certificate fingerprint available.
- Real Digital Asset Links file prepared and deployed.

## Required Production URL

Use the final production HTTPS URL before generating the TWA project:

```text
https://your-production-domain.example
```

Do not initialize the Android project from localhost, a temporary tunnel, or a staging URL unless the generated project is clearly marked as disposable.

## HTTPS Requirement

TWA packaging and Android camera access require HTTPS in production. The QR scanner should be tested on Chrome Android and in the generated TWA only after the production SSL certificate is active and all application assets load over HTTPS.

## PWA Manifest Validation

Before running Bubblewrap, verify that the production manifest includes:

- App name and short name.
- Description.
- Start URL: `/admin/mobile-technician-dashboard?source=pwa`.
- Scope: `/`.
- Display mode: `standalone`.
- Portrait orientation.
- Theme color and background color.
- Launcher and maskable icons.
- QR scanner and dashboard shortcuts where supported.

Use browser developer tools or a PWA validation tool against the production URL.

## Bubblewrap Installation Overview

Bubblewrap commands are developer-machine commands, not Laravel server commands. Run them on the workstation used to generate Android packaging artifacts.

```bash
npm install -g @bubblewrap/cli
```

Bubblewrap requires a working Node.js environment and Android build tooling on the developer machine.

## TWA Project Initialization Steps

Initialize the TWA project from the production manifest:

```bash
bubblewrap init --manifest=https://your-production-domain.example/manifest.webmanifest
```

During initialization, review:

- Application name.
- Launcher label.
- Package name.
- Start URL.
- Theme color.
- Icon assets.
- Signing key path.
- Version code and version name.

Recommended project folder placeholder:

```text
android-twa/
```

Do not commit release signing secrets.

## Package Name Guidance

Use an organization-owned, stable package name. Recommended values:

- App name: `AI Based Equipment Inventory and Maintenance`
- Short app name: `AI Equipment`
- Suggested package name: `edu.snsu.delcarmen.cfs.cmms`
- Suggested start URL: `https://your-production-domain.example/admin/mobile-technician-dashboard?source=pwa`
- Suggested scope: `https://your-production-domain.example/`
- Suggested launcher label: `AI Equipment`

The package name should be final before Play Store publishing because changing it later creates a separate Android app identity.

## App Signing Key Guidance

Create a release signing key and store it securely. Keep a separate backup outside the project repository. Never commit keystores, passwords, or signing configuration with real secrets.

Placeholder release key information:

```text
Keystore path: /secure/path/ai-equipment-release.keystore
Key alias: ai-equipment-release
```

## SHA-256 Fingerprint Generation

After creating the release signing key, generate the SHA-256 fingerprint. Example:

```bash
keytool -list -v -keystore /secure/path/ai-equipment-release.keystore -alias ai-equipment-release
```

Copy the `SHA256` fingerprint for Digital Asset Links.

## Digital Asset Links Setup

Digital Asset Links proves that the website and Android package are owned by the same organization. The final file must be served from:

```text
https://your-production-domain.example/.well-known/assetlinks.json
```

This repository includes a template only:

```text
public/.well-known/assetlinks.template.json
```

Do not use the template as the production file until placeholders are replaced.

## assetlinks.json Finalization Guide

Create `public/.well-known/assetlinks.json` only when real production values are known.

Replace these placeholders:

- Production domain: `https://your-production-domain.example`
- Package name: `edu.snsu.delcarmen.cfs.cmms`
- SHA-256 certificate fingerprint: `REPLACE_WITH_RELEASE_CERTIFICATE_SHA256_FINGERPRINT`

Final structure:

```json
[
  {
    "relation": [
      "delegate_permission/common.handle_all_urls"
    ],
    "target": {
      "namespace": "android_app",
      "package_name": "edu.snsu.delcarmen.cfs.cmms",
      "sha256_cert_fingerprints": [
        "REPLACE_WITH_REAL_SHA256_FINGERPRINT"
      ]
    }
  }
]
```

Verify the final file in a browser before building release artifacts.

## APK Build Command

Build an APK from the generated TWA project:

```bash
bubblewrap build
```

Expected APK output depends on the generated Bubblewrap project structure. Record the final output path during the build.

## AAB Build Command

Build an Android App Bundle when preparing for Play Store release:

```bash
bubblewrap build --release
```

Confirm the generated AAB is signed with the intended release key before upload.

## Local Device Testing

Install the generated package on a test Android device:

```bash
bubblewrap install
```

Then verify:

- App opens to the mobile technician dashboard.
- Admin login works.
- QR scanner camera prompt appears over HTTPS.
- Manual QR lookup fallback works.
- Offline queue is visible.
- Offline sync resumes after reconnect.
- Equipment photos and generated QR images load.

## Chrome Android Testing

Before testing the TWA, test the production PWA directly in Chrome Android:

- Open the production domain.
- Verify install prompt behavior.
- Open the mobile technician dashboard.
- Open QR scanner.
- Open offline queue.
- Confirm admin pages are not replaced by offline shell.

## Bubblewrap Validation

Run validation from the generated TWA project:

```bash
bubblewrap validate
```

Review warnings for manifest, Digital Asset Links, icons, signing, and start URL issues.

## Android Build Output Checklist

Expected packaging artifacts:

- Generated Android project folder.
- APK file for direct installation testing.
- AAB file for future Play Store release.
- Release signing key and backup.
- SHA-256 signing certificate fingerprint.
- Final `assetlinks.json` deployed to production.
- Build logs and version notes.

## Common Errors

- `assetlinks.json` returns 404: deploy the final file at `/.well-known/assetlinks.json`.
- Package name mismatch: ensure Bubblewrap package name matches `assetlinks.json`.
- SHA-256 mismatch: use the release signing certificate fingerprint, not a debug key.
- Camera does not open: verify HTTPS and Android permission prompt behavior.
- Admin opens offline shell: verify service worker exclusions for `/admin`, `/filament`, and `/livewire`.
- QR or photos broken: verify `/storage` files are accessible on production.

## Rollback Plan

- Keep the Laravel PWA deployment unchanged while testing Android packaging.
- If TWA validation fails, fix production PWA or Digital Asset Links first.
- If an APK/AAB build fails, discard the generated Android project and rerun Bubblewrap after correcting manifest or signing configuration.
- Do not publish to Play Store until production URL, signing, asset links, and device tests are verified.
- Maintain backups of signing keys and generated release artifacts outside the repository.
