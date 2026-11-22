# Salon Manager SaaS

Multi-tenant salon management platform: GST billing/POS with staff commissions, sales and P&L reports (CSV/Excel/PDF), WhatsApp campaigns and automations with a credits ledger, a WhatsApp assistant with QR check-in, an "ask your salon" chat, and an offline-capable PWA with a Capacitor Android scaffold.

**Stack:** Laravel 13 (PHP 8.3) · Vue 3 + Vuetify + Pinia · MySQL · Sanctum · Spatie permissions.

> WhatsApp, SMS, payments, LLM, speech-to-text and push are **local fakes** behind interfaces (see [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md)). Messages go to a `message_log` table, not to real phones.

## Screenshots

| | |
|---|---|
| **Owner dashboard**: KPIs, 30-day trend, staff leaderboard, shop comparison, "Ask your salon" | ![Dashboard](docs/screenshots/01-dashboard.png) |

| Billing counter | Reports |
|---|---|
| ![POS](docs/screenshots/02-billing-pos.png) | ![Reports](docs/screenshots/03-reports.png) |

| Marketing campaigns | WhatsApp assistant (simulator) |
|---|---|
| ![Marketing](docs/screenshots/04-marketing-campaigns.png) | ![Bot](docs/screenshots/05-whatsapp-assistant.png) |

| Customers | Expenses |
|---|---|
| ![Customers](docs/screenshots/06-customers.png) | ![Expenses](docs/screenshots/07-expenses.png) |

| Super admin | Role permissions | Mobile |
|---|---|---|
| ![Admin](docs/screenshots/08-super-admin.png) | ![Permissions](docs/screenshots/09-permissions.png) | <img src="docs/screenshots/10-mobile-dashboard.png" width="220"> |

## Run locally

```bash
# backend (MySQL on 127.0.0.1:8889, db salon_saas; see backend/.env.example)
cd backend && composer install && php artisan migrate && php artisan demo:seed && php artisan serve

# frontend
cd frontend && npm install && npm run dev      # http://localhost:5173
```

Demo logins: [docs/CREDENTIALS.md](docs/CREDENTIALS.md) · demo script: [docs/DEMO.md](docs/DEMO.md) · mobile: [docs/MOBILE.md](docs/MOBILE.md) · roadmap: [docs/NEW-EXPECTATIONS-PLAN.md](docs/NEW-EXPECTATIONS-PLAN.md).
