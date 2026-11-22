# MVP Build Plan — Salon Management SaaS

Execution checklist for the 12-week MVP. Strategy, architecture and rules live in [PLAN.md](../PLAN.md); product behaviour lives in [FEATURE-SPECIFICATION.md](FEATURE-SPECIFICATION.md). This file answers: **who builds what, in what order, and when is it done.**

**Team:** Dev A (backend-led), Dev B (frontend-led), Designer/QA (part-time). Both devs pair on the critical path (POS, commission). Sprints are 2 weeks; Sprint 0 is 3–4 days before Week 1.

## 0. Before writing code (Week 0, parallel to Sprint 0)

| Task | Owner | Why now |
|---|---|---|
| Apply for Meta WhatsApp Business verification, sender number, first templates | Founder/Dev A | Approval takes days to weeks; gates Sprint 5 |
| Start India SMS DLT registration (entity, header, templates) | Founder | Mandatory before any SMS |
| Create Razorpay account, KYC, test-mode subscription plans | Founder | Gates Sprint 5 |
| Confirm scope decision: coupons, feedback, automations, GST/P&L in MVP or Phase 2 | Founder | See PLAN.md scope check |
| Choose WhatsApp route: Meta Cloud API vs BSP (Gupshup/Interakt/AiSensy) | Founder + Dev A | Decide by end of Sprint 1 |
| Verify Laravel 13 compatibility of Sanctum, Spatie Permission, Horizon, Reverb, dompdf, Maatwebsite Excel on PHP 8.3 | Dev A | PLAN.md requires it before first install |
| Design: POS tablet flow, 360 px phone layout, thermal receipt sketch | Designer | POS is the most-used screen |

## 1. Sprint 0 — Foundations (3–4 days)

**Dev A**
- [x] Laravel 13 app in `/backend`, modular folders per PLAN.md §3, `/api/v1` routing, central JSON response and validation error format.
- [~] Sanctum, CORS, Redis cache/queue, logging with tenant/shop/user/request-id, UTC timezone, health endpoint.
- [x] `BelongsToTenant` global scope, base migration conventions (`tenant_id`, `shop_id` where relevant, soft deletes).
- [x] `X-Shop-Id` middleware: unknown or mismatched shop returns the same error with no data leakage.
- [x] Spatie Permission with teams = shop; Shop Owner as tenant-level role; seed the 5 roles and default permissions.
- [x] `Money` value object (integer paise) and tests.
- [~] Seed plans (Free/Basic/Pro) and the 40–50 service onboarding template.

**Dev B**
- [x] Vue 3 + Vite + TS + Vuetify 3 + Pinia + router + vite-plugin-pwa in `/frontend`.
- [x] Typed API client module, shop-context store (adds `X-Shop-Id`), desk and counter layouts, ₹ 1,23,456 formatter.
- [~] ESLint/Prettier, Vitest, Playwright skeleton.

**Shared**
- [x] `docker-compose.yml` (PHP, MySQL 8, Redis, Mailpit), GitHub Actions running backend and frontend checks.
- [x] Test helpers for tenant A / tenant B and cross-shop access.

*Status legend: [x] done, [~] partly done (see notes below).*

**Exit:** clean local setup; CI green; cross-tenant and cross-shop calls denied or scoped in tests.

## 2. Sprint 1 — Auth, tenancy, shops, roles (Weeks 1–2)

| Dev A | Dev B |
|---|---|
| OTP send/verify (rate limited, provider behind an interface; log driver in dev), email/password login, `/me` | Login/OTP screens, token handling, route guards |
| Tenant + owner signup, business name | Onboarding wizard: business → first shop → template services → staff → bill format |
| Shop CRUD and settings (GSTIN, invoice prefix, GST mode, rounding, timezone, hours, weekly off) | Shop settings UI, shop switcher, owner "All shops" option |
| `shop_counters`, plan limits enforced at API (shops, staff) | Plan-limit error states in UI |
| Users/roles API with default grants | Team management screen |

**Status (implemented):** OTP + email login, business/shop onboarding, shop CRUD/settings, plan limits, users/roles with Spatie teams = shop, shop switcher, settings and team screens. Wizard steps for template services and staff are placeholders until Sprint 2. SMS uses a log gateway until Sprint 5. The trial runs on the Pro plan.

**Done when:** an owner signs up by OTP, finishes the wizard, adds a second shop, and a plan-limit breach is rejected by the API (not only the UI). Moved out of this sprint: Razorpay, wallet, permission-customization UI.

## 3. Sprint 2 — Catalog and staff (Weeks 3–4)

| Dev A | Dev B |
|---|---|
| Categories, services, shop price overrides, products, stock, combos | Category drag-sort, service/product forms, price override grid |
| CSV import (validation report) and export; default service seeding | Import wizard with row errors |
| Staff profile, shop assignment, active/inactive, encrypted bank/UPI, ID-proof upload to object storage | Staff screens, attendance (check-in/out, leave), monthly sheet |
| Commission rules and precedence engine: staff+service → service → staff → shop default; flat, fixed, slab; product commission; before/after discount and GST setting; tips rule | Commission rule editor (hidden from Cashier/Staff) |
| Rule snapshot structure for `invoice_item_staff` | Staff self-view skeleton |

**Status (partly implemented):** categories, services (+ per-shop price override API), products with stock, staff with shop assignment and default commission, staff+service/service/staff commission precedence (percent and fixed). Not yet: combos, CSV import/export, attendance, slab rules, bank/ID fields, category drag-sort.

**Done when:** commission tests cover each precedence level, splits and slab calculations; Cashier cannot see commission rates (policy test).

## 4. Sprint 3 — Billing / POS (Weeks 5–6) — critical path

| Dev A | Dev B |
|---|---|
| `CreateInvoice` action: one transaction, sequential invoice number via locked `shop_counters` per shop and financial year, idempotency key | POS screen driven by a composable (draft state, totals, validation), not one large component |
| Totals engine: line/bill discount (% or ₹), role discount limits, GST inclusive/exclusive, CGST/SGST, rounding, in paise | Customer search by mobile + quick-add (name + mobile only) |
| Payments: cash, UPI, card, wallet/credit, split; due/credit and collect-due | Category tiles, search, last-used worker chip, per-line staff split |
| Tips per worker; commission written at save with snapshot | Payment sheet with split payments; tips field |
| Draft/hold bills, bill history/search, edit-after-save as revision with reason and audit (Manager+) | Hold tickets list, bill history, reprint |
| A4 PDF (queued, stored in object storage), 58/80 mm print template, public invoice link | Print preview, `wa.me` share, 360 px/tablet layouts |

**Status (partly implemented):** `CreateInvoice` (single transaction, locked per-shop/financial-year numbering, idempotency key, commission snapshot, stock decrement), totals engine (line/bill discount, GST inclusive/exclusive, rounding, tips), cash/UPI/card payments with due balance, invoice list/detail, POS screen with customer quick-add, staff per line, browser print. Not yet: hold/draft bills, split payments UI, role discount limits, bill revisions, PDF/thermal templates, public link, concurrency test.

**Gate:** add customer → 3 lines → assign staff → split discount/payment → print/share, in under 30 s with a known customer. Concurrency test: parallel saves yield no duplicate or missing invoice numbers. No WhatsApp/SMS/PDF work in the request path.

## 5. Sprint 4 — Refunds, cash, payouts, customers (Weeks 7–8)

| Dev A | Dev B |
|---|---|
| `RefundInvoice` (full/partial, manager PIN, reason, audit, commission reversal) | Refund flow UI, PIN prompt |
| Expenses; cash register open/close, expected vs counted | Expense entry, day-close screen and report |
| Payout preview/finalize: fixed salary, commission, tips, advances, deductions; paid-period lock; A4 export | Payout statement UI, advances/deductions entry, staff self-view (today, month, pending) |
| Customer profile, tags, source, consents, computed fields, segments (configurable thresholds), dues | Customer list with filters, profile with visit timeline |
| CSV import with duplicate merge by mobile, owner-only export, Cashier phone masking, opt-out enforcement | Import/export UI |
| `daily_shop_summary` / `daily_staff_summary` tables and nightly jobs | Staff work-tracking views (day/week/month) |

**Gate:** a finalized worker statement and a historical invoice stay unchanged after commission rules are edited.

## 6. Sprint 5 — Subscriptions, wallet, messaging (Weeks 9–10)

| Dev A | Dev B |
|---|---|
| Razorpay subscriptions: trial, upgrade, failure/grace; webhook signature check and event dedupe | Plan/billing page |
| Credit wallet: top-up, debit, usage history | Wallet UI, low-credit alert |
| Messaging module behind provider interfaces (WhatsApp, SMS); queue with throttling, retries, delivery webhooks, dead-letter alerts | Template CRUD/preview/status, campaign builder (audience count, credit estimate, schedule) |
| Consent filtering and permanent opt-out exclusion | Campaign stats view |
| Provider-sent invoice delivery replaces `wa.me` link | — |
| Trim-candidates (confirm scope first): feedback link + low-rating alert, coupons, birthday/win-back/due/thank-you automations, attributed revenue | Matching UI |

**Status (implemented with local fakes):** credits wallet + ledger, templates, campaigns (estimate, send, report, attribution), consent/opt-out enforcement, signed idempotent webhooks, automations, coupons. Real WhatsApp/SMS/Razorpay are not connected; see [INTEGRATIONS.md](INTEGRATIONS.md).

**Done when:** opted-out customers are never sent anything; failed sends retry and surface status; webhooks are idempotent.

## 7. Sprint 6 — Dashboard, reports, hardening, pilot (Weeks 11–12)

- [x] Dashboard KPIs, trend, top services/staff, payment split, shop comparison, alerts. (Reverb live tiles: not built.)
- [x] Reports with filters and CSV/Excel/PDF export: sales, staff, service, payment mode, customer, expenses, P&L, GST, discounts, dues, campaign ROI.
- [~] Permission-customization UI: super admin can edit role permissions; an owner-level per-salon override is not built.
- [ ] Privacy workflow: owner-requested data export and deletion.
- [~] Performance: dashboard median 0.72 s at 100k invoices (`php artisan perf:dashboard`). Invoice-save p95 not yet measured.
- [ ] Security review: OTP/login/webhook rate limits, uploads, encrypted fields, export and audit endpoints.
- [ ] Backups (daily, 30-day retention) and a tested restore; Sentry; queue and scheduler health alerts; Forge/Ploi deploy with atomic releases.
- [~] PWA install and offline queue done; Android Chrome thermal print and a real-device weak-network test still to do.
- [ ] Seed and onboard 3 pilot salons.

**Gate:** owner and manager run the pilot without spreadsheet reconciliation, and the sign-off checklist in PLAN.md §10 passes.

## 8. Definition of done (every ticket)

- Policy/permission check and tenant + shop scoping present, with a cross-tenant test.
- Money in paise through `Money`; no float math.
- Sensitive actions (edit, refund, discount override, price change, payout, permission change) write audit entries.
- Slow or external work is queued, with IDs in the payload, not copies.
- API, typed frontend module and tests land together; server-side pagination for lists.
- Works at 360 px and with keyboard on desktop.

## 9. If the schedule slips

1. Protect Sprint 3 and the Sprint 4 gate; do not start Sprint 5 integrations before the POS gate passes.
2. Trim in this order: win-back/birthday automations → feedback → coupons → P&L → GST report.
3. Never trim: tenant isolation tests, invoice number locking, commission snapshots, opt-out enforcement, backup restore test.
