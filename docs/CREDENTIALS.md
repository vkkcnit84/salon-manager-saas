# Dummy credentials (LOCAL DEV ONLY)

Never use these on a real server. Created by `php artisan demo:seed` (run in `backend/`).

App: http://localhost:5173 (frontend) · API: http://localhost:8000 · Sign in with the **Email / password** tab.

## App logins

| Role | Name / Salon | Email | Password |
|---|---|---|---|
| Platform super admin | — | `admin@salon.test` | `Admin@12345` |
| Owner | Meera — Urban Glow (Pro, 2 shops) | `meera@urbanglow.test` | `Password@123` |
| Manager | Suresh — Urban Glow | `suresh@urbanglow.test` | `Password@123` |
| Cashier | Anita — Urban Glow | `anita@urbanglow.test` | `Password@123` |
| Owner | Imran — Royal Cuts (Basic) | `imran@royalcuts.test` | `Password@123` |
| Owner | Pooja — Bella Beauty (Free trial, low credits) | `pooja@bellabeauty.test` | `Password@123` |
| Legacy demo owner | — | `demo@salon.test` | `Password@123` |

## Infrastructure

| Item | Value |
|---|---|
| MySQL host / port | `127.0.0.1` : `8889` (MAMP) |
| MySQL database | `salon_saas` |
| MySQL user / password | `root` / `root` |

## Dev-only secrets (backend/config/messaging.php)

| Item | Value |
|---|---|
| WhatsApp webhook signing secret | `local-dev-secret` |
| WhatsApp webhook verify token | `local-verify-token` |
| Demo coupon code | `WELCOME10` |

## Other

- Mobile-number OTP login is also available; OTPs are fake and logged locally, not sent.
- Create another super admin: `php artisan admin:create you@x.com Password1`
- Replace every value above before any real deployment.
