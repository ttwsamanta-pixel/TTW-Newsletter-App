# Flutter mobile setup

## Full manager 0.3.0

Version 0.3.0 / build 6 requires the matching backend and migration 003. The installed earlier app remains compatible with existing routes. The new APK adds full manager screens and native HTML save/copy/publishing. No database or publishing credentials are included in Flutter. See docs/UPDATE_0.3.0.md and HTML_PUBLISHING.md. iOS source is provided; compilation/signing requires macOS/Xcode and an Apple team. The optional web runner is for local visual verification, not a replacement for the PHP website.


## Version 0.2.2 / build 5

Deploy the matching backend to use uploads/temporary/YYYY/Mon for new working images. Both current and previous mobile clients accept the paths returned by the server. Install the updated APK over the existing app after deployment.


## Current Android update

v0.2.1 / build 4 uses the server image fallback endpoint for uploaded images and newsletter previews. Deploy the matching full web ZIP inside /New before installing the APK. Use the existing website account. This release has automated validation; phone acceptance follows backend deployment.


The `mobile/` directory contains Flutter source plus Android/iOS runners generated with Flutter 3.47.5 / Dart 3.13.4. It does not modify the old APK in `app/`.

```sh
cd mobile
flutter pub get
flutter analyze
flutter test
flutter run --dart-define=API_URL=https://newsletter.travelandtourworld.com/New/api/index.php
```

API_URL is a build-time public URL, not a secret. Use the complete PHP controller URL without a route query. Only HTTPS is accepted. Point a staging build at a trusted HTTPS staging server rather than disabling certificate validation. Tokens use flutter_secure_storage; no MySQL credentials or scraping code are shipped to the client.

Version 0.1.1 defaults to the deployed `/New/api/index.php` endpoint. Replace the older 0.1.0 APK, which used `/api/index.php` and could show an unexpected HTML response. Use the same active website user account and password; no mobile-only default credentials are created.

## Screens and state

Auth is a ChangeNotifier with secure token restoration, login, seven-day server expiry and revocation. The client handles network/server/validation errors and returns to login when authentication expires. Logout requires the revocation request to succeed. Dashboard/history/category/settings share one authenticated API client. Creation uses local feature state for its input/review/details stages. Job progress persists on the server, polls every three seconds and can be reopened from recent jobs. Timers stop when leaving screens. Metadata and images come from the PHP worker only.

The mobile review supports replace/remove/reorder within sections before processing. Original extracted titles, descriptions, image and URL status appear in per-article expansion cards after processing. Failed/warning articles can be retried; missing titles block generation. Preview retrieves authenticated renderer HTML and loads it into a JavaScript-disabled WebView; tokens never appear in URLs or HTML. Links are disabled within this preview. Native Edit Draft updates subject/description, header and Pick News text, preserving images and link collections; the existing web editor provides the full six-step editing surface.

Promotions can be chosen from Gallery, Camera or File and upload directly to PHP. The app retains only the returned server path/signature and preview URL. iOS camera/photo descriptions are configured. Android uses the system photo picker and requires Internet permission. Verify permission denial/camera availability on real hardware. The visual theme uses the existing TTW logo and the reference's navy/white/red palette; background travel photography is not included in the source assets.

## Android

Install Android Studio/SDK and a compatible JDK. Accept SDK licenses yourself with `flutter doctor --android-licenses` after reviewing their terms. Use an emulator or physical device for `flutter run`.

```sh
flutter build apk --debug --dart-define=API_URL=https://your-staging-host/api/index.php
```

Artifact: `build/app/outputs/flutter-apk/app-debug.apk`. For production configure your own Android keystore via android/key.properties (storeFile, storePassword, keyAlias, keyPassword); keep that file and keystore out of source control. Release builds are not configured to silently use debug keys.

```sh
flutter build appbundle --release --dart-define=API_URL=https://your-production-host/api/index.php
```

The generated Windows Gradle wrapper was corrected to quote variable assignments because this workspace's path includes `&`. If another SDK tool cannot handle that path, copy the mobile source to a simple path such as `D:/ttw-mobile` and run `flutter pub get` there; do not move or delete the original workspace.

## iOS

Build on macOS with Xcode and the Flutter iOS toolchain. Open ios/Runner.xcworkspace after resolving native dependencies. Configure your Apple Development Team, bundle ID and signing/provisioning. Keychain entitlement and camera/photo descriptions are included; confirm capabilities against your provisioning profile.

```sh
flutter build ios --release --dart-define=API_URL=https://your-production-host/api/index.php
```

Archive/distribute using your Apple account and standard Xcode process. iOS builds cannot be produced or signed on this Windows host. Test secure token persistence, logout, WebView, permissions and uploads on Android and iOS before distributing.

Flutter project/tooling references: [create an app](https://docs.flutter.dev/reference/create-new-app), [platform setup](https://docs.flutter.dev/install). Runtime networking follows PHP's [cURL options](https://www.php.net/manual/en/function.curl-setopt.php/) with manual redirect validation.

## Version 0.2.0

Update the matching PHP backend first, then install the 0.2.0 APK (build 3). All-links creation remains available. The new Section by Section workspace has Basic, Header, Pick News, More News, Categories and Promotion tabs and can resume an existing draft from Preview. More/Categories can arrive before Header/Pick. Email Title is optional while drafting; Hidden Preview Text is filled from Header body. Existing account/token storage remains the same. See docs/UPDATE_0.2.0.md.
