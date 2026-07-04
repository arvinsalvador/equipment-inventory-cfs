# Android Release Checklist

Use this checklist before generating or distributing a real APK/AAB.

## Web and PWA Verification

- HTTPS verification completed on the final production domain.
- Digital Asset Links verification completed at `/.well-known/assetlinks.json`.
- PWA manifest verification completed from the production URL.
- Service worker verified as active and scoped safely.
- Icon verification completed for launcher and maskable icons.
- Splash verification completed using manifest theme and background colors.

## Android Build Verification

- Bubblewrap configuration reviewed.
- Release signing key prepared outside the repository.
- SHA-256 fingerprint added to production Digital Asset Links.
- APK build completed.
- AAB build completed.
- Build artifacts stored in a secure release location.

## Device Testing

- Physical Android phone testing completed.
- Android tablet testing completed.
- Chrome Android TWA launch behavior verified.
- Offline testing completed.
- Offline queue sync testing completed.
- QR scanner testing completed over HTTPS.
- Camera permission prompts verified.
- Maintenance request workflow testing completed.
- Work order workflow testing completed.
- Filament login and authenticated navigation verified.

## Release Gate

Do not publish or distribute the build until every required production value is final and device validation is complete.
