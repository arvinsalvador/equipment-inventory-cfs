# Android TWA Bootstrap

## Project Purpose

This directory prepares the existing Laravel Progressive Web App for future Android APK and AAB generation using a Trusted Web Activity (TWA). Phase 15C creates bootstrap documentation and templates only. It does not generate a full Android Studio project, add native business logic, replace the PWA, or publish to the Play Store.

## TWA Architecture

The Android package should open the production HTTPS PWA inside a Chrome-powered Trusted Web Activity. Laravel remains the source of truth for authentication, Filament screens, offline queue behavior, QR scanning, maintenance workflows, and service-worker behavior.

Expected flow:

1. Laravel PWA is deployed to the final HTTPS production domain.
2. Bubblewrap reads the deployed web manifest.
3. A TWA Android project is generated from the manifest and this repository's package identity guidance.
4. The release signing certificate SHA-256 fingerprint is added to Digital Asset Links.
5. Android builds are validated on real devices before release.

## Expected Android Studio Version

Use the current stable Android Studio release available at build time. Android Studio Ladybug or newer is recommended for modern Gradle, SDK, emulator, and signing tooling.

## SDK Recommendations

- Minimum SDK: Android 8.0, API 26 or newer.
- Target SDK: The latest stable Android SDK required by Google Play at release time.
- Compile SDK: Match the latest installed stable SDK on the build workstation.

## Java/Kotlin Recommendation

Bubblewrap-generated TWA projects are primarily configuration-driven Android projects. Prefer the generated Java/Kotlin defaults from Bubblewrap and avoid custom native code unless a later phase explicitly requires it.

Use:

- Java 17 or the Java version required by the generated Gradle wrapper.
- Kotlin only if the generated Bubblewrap project includes it.
- No native business logic in Phase 15C.

## Folder Structure

    android/
    |-- README.md
    |-- bubblewrap.config.template.json
    |-- SIGNING_GUIDE.md
    `-- RELEASE_CHECKLIST.md

Future generated Android Studio files should be placed under a dedicated generated project folder, for example `android/twa/`, after production HTTPS, signing, and Digital Asset Links values are final.

## Current Bootstrap Status

- Android Studio project generation: Deferred.
- APK generation: Deferred.
- AAB generation: Deferred.
- Real signing keys: Deferred.
- Play Store publishing: Deferred.
