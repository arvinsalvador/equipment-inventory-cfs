# Android Signing Guide

Phase 15C does not create or commit Android signing keys. This guide documents the signing preparation steps for a future real APK/AAB build.

## Debug Keystore

Android Studio and Gradle can use a local debug keystore for development builds. Debug keys are not suitable for release, production Digital Asset Links, or Play Store publishing.

Typical debug fingerprint command:

    keytool -list -v \
      -keystore ~/.android/debug.keystore \
      -alias androiddebugkey \
      -storepass android \
      -keypass android

## Release Keystore

Create a release keystore only on the secure build workstation used for production releases. Do not commit the keystore or passwords to this repository.

Example release keystore command:

    keytool -genkeypair \
      -v \
      -keystore release-key.jks \
      -alias snsu-cfs-cmms \
      -keyalg RSA \
      -keysize 2048 \
      -validity 10000

Store the keystore outside the repository and reference it through local Gradle properties or CI secrets in a later phase.

## SHA-256 Fingerprint Generation

Digital Asset Links requires the SHA-256 fingerprint of the certificate used to sign the Android app.

    keytool -list -v \
      -keystore /secure/path/release-key.jks \
      -alias snsu-cfs-cmms

Copy the SHA-256 value into the final production `/.well-known/assetlinks.json` only after the package name and signing certificate are final.

## Backup Recommendations

- Keep at least two encrypted backups of the release keystore.
- Store backups in separate secure locations.
- Record the key alias and creation date in a private release document.
- Use a password manager or secrets manager for keystore passwords.
- Restrict access to release managers only.

## Security Considerations

- Never commit `.jks`, `.keystore`, passwords, or signing property files.
- Never use the debug keystore for production Digital Asset Links.
- Rotate access credentials if a workstation or keystore password is exposed.
- Treat the release keystore as a production secret.
- Verify the fingerprint every time a new signing key is introduced.
