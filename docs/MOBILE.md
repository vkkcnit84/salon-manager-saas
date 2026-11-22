# Mobile — PWA and Android (Capacitor)

The same Vue app ships three ways: a website, an installable **PWA**, and an **Android app** (a Capacitor wrapper around the built `dist/`). No Kotlin is written; Android Studio is only used to sign and build.

## 1. PWA (works today)

- `vite-plugin-pwa` generates `manifest.webmanifest` and `sw.js` on `npm run build`.
- `start_url` is `/pos`, so the installed app opens on the billing counter. Icons are `public/pwa-192.png`, `pwa-512.png` and a maskable 512.
- The service worker caches the **app shell only**. API calls are never cached, so numbers are always live.
- **Install banner:** `src/mobile/InstallBanner.vue` shows "Install app" when Chrome offers installation, and remembers a dismissal.
- **Weak network:** a failed bill save shows the error and can be retried safely, because every bill carries an idempotency key.

## 2. Offline billing

- If the device has no connection when a bill is saved, `src/mobile/offlineQueue.ts` stores it in **IndexedDB** with a client UUID and shows "N bills waiting to sync".
- It syncs automatically when the browser goes online and every 30 s. The UUID is sent as `idempotency_key` (`client_uuid` is accepted as an alias), so a retry can never create a duplicate invoice.
- **Invoice numbers are assigned by the server at sync time**, so the official sequence never has gaps. The counter shows a provisional `PENDING-XXXXXX` reference until then. Agree the receipt wording with your pilot salons.
- If the server rejects a queued bill (validation or permission), it stays in the queue with its error instead of being dropped.
- Limits: it stores bills only. Customer quick-add and refunds still need a connection.

## 3. Android app

Prerequisites: Node 20+, Android Studio (includes the SDK and a JDK), a Google Play developer account for release.

```bash
cd frontend
cp .env.example .env            # set VITE_API_BASE=https://api.yourdomain.com  (absolute https URL, no trailing slash)
npm install
npm run build                   # builds dist/ with the API base baked in
npx cap sync android            # copies dist/ and plugins into android/
npx cap open android            # opens Android Studio
```

The `android/` folder is already created. If you ever need to recreate it: `npx cap add android`.

Run on a device from Android Studio (▶), or build a release:

1. **Build ▸ Generate Signed App Bundle / APK ▸ Android App Bundle (AAB)**.
2. Create a keystore the first time. **Never commit it.** Keep two copies in a password manager or secure drive; losing it means you can no longer update the app (unless you enrolled in Play App Signing, which is recommended).
3. Upload the `.aab` in Play Console.
4. **Closed testing:** new personal developer accounts must run a closed test with at least 12 testers for 14 days before production access. Plan for this in your launch schedule.

### Versioning
Bump both in `android/app/build.gradle` before **every** upload: `versionCode` (integer, must increase each release) and `versionName` (human readable, e.g. `1.0.3`).

### appId
`capacitor.config.ts` uses `com.salonmanager.app`. Change it to your brand **before** the first Play upload; it cannot change afterwards. After changing, delete `android/` and run `npx cap add android` again.

### API and CORS
The WebView origin is `https://localhost` (Capacitor `androidScheme: https`). Add that origin to Laravel's CORS config (`config/cors.php` `allowed_origins`) for the production API. Auth uses the Sanctum bearer token, so no cookies are needed.

## 4. Native features

| Feature | Plugin | Status |
|---|---|---|
| Token in native storage | `@capacitor/preferences` | Wired (`src/mobile/native.ts`) |
| Android back button | `@capacitor/app` | Wired |
| Share invoice | `@capacitor/share` | Wired (`shareInvoice`) |
| Push notifications | `@capacitor/push-notifications` | Token registration wired → `POST /api/v1/devices`. **Sending is a stub** (`App\Mobile\FcmSender` logs only). |
| Bluetooth thermal printer | community BLE plugin | ESC/POS builder done (`src/mobile/escpos.ts`, 58/80 mm). The plugin bridge is **not installed**; until then it falls back to the browser print dialog. |

### Turning on push later
1. Create a Firebase project, add the Android app (`appId`), and download `google-services.json` into `android/app/`.
2. Implement `FcmSender::toUser()` with the FCM HTTP v1 API (OAuth2 service account). Device tokens are already stored per user in `device_tokens`.

### Turning on the Bluetooth printer later
Install a Capacitor Bluetooth plugin, pair the printer, and set `window.SalonPrinter = { write }` that sends the bytes to it. `printReceipt()` already prefers `window.SalonPrinter` over the print dialog.

## 5. Handy commands

| Task | Command |
|---|---|
| Rebuild + sync after web changes | `npm run build && npx cap sync android` |
| Run on a connected phone | `npx cap run android` |
| Update Capacitor | `npm i @capacitor/core@latest @capacitor/cli@latest @capacitor/android@latest && npx cap sync` |
