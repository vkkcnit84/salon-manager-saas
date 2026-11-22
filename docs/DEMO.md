# Demo accounts and data

Local app: http://localhost:5173 (frontend) · http://localhost:8000 (API). Sign in with the **Email / password** tab.

| Role | Email | Password |
|---|---|---|
| Platform super admin | `admin@salon.test` | `Admin@12345` |
| Owner — Urban Glow (Pro, 2 shops) | `meera@urbanglow.test` | `Password@123` |
| Manager — Urban Glow | `suresh@urbanglow.test` | `Password@123` |
| Cashier — Urban Glow | `anita@urbanglow.test` | `Password@123` |
| Owner — Royal Cuts (Basic) | `imran@royalcuts.test` | `Password@123` |
| Owner — Bella Beauty (Free trial, low credits) | `pooja@bellabeauty.test` | `Password@123` |

## Commands (run in `backend/`)
| Task | Command |
|---|---|
| Create demo salons, teams, 60 days of bills, templates, campaigns, expenses | `php artisan demo:seed` |
| Add bills for days since the last one (run this before a demo so "Today" isn't empty) | `php artisan demo:seed --topup` |
| Rebuild daily summary tables | `php artisan reports:rebuild --from=2026-09-01 --to=2026-11-21` |
| Run automations / nightly summary / alerts now | `php artisan automations:run` · `bot:nightly-summary --force` · `bot:alerts` |
| Dashboard load test at 100k invoices (creates a throwaway tenant) | `php artisan perf:dashboard --seed=100000` then `--cleanup` |
| New super admin | `php artisan admin:create you@x.com Password1` |
| Run the scheduler locally | `php artisan schedule:work` |

## Good things to show a salon owner
1. **Dashboard** — today's sales, 30-day trend, staff leaderboard, shop comparison, and **Ask your salon** ("Is mahine Ravi ka commission kitna hua?").
2. **WhatsApp assistant** — chat as the owner; try *Send offer* (draft + Send/Skip buttons); scan the **QR check-in** as a new customer, then see them on the billing screen.
3. **Marketing** — launch a win-back campaign (audience → template → cost → confirm), then open its report; check **Message log** for what was "sent".
4. **Billing** — apply coupon `WELCOME10`; switch the browser offline, save a bill, go back online and watch it sync.
5. **Reports** — Profit & loss, GST and Campaign ROI; export to Excel/PDF.
6. **Super admin** — change a salon's plan or suspend it; edit what each role may do.
