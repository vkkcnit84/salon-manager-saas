# Salon Management SaaS — Development Plan

## 1. Delivery target

Build a secure, multi-tenant MVP for 1–20 shops per tenant, optimized for Indian salon counter workflows and WhatsApp-first communication. Target launch is 12 weeks with 2 full-stack developers, 1 part-time designer/QA, and 2-week sprints.

### MVP outcome
- A salon owner can onboard a shop and begin billing on day one.
- A cashier can complete a 3-service bill with a known customer in under 30 seconds.
- The system automatically calculates worker work, commission, tips, payouts, GST, dues, and reports.
- Tenant data, shop access, roles, and plan limits are enforced by API middleware and policies.
- The MVP runs as a responsive PWA; offline bill queueing is deferred to Phase 2. Until then the POS must tolerate weak networks through fast retries, idempotency keys, and a clear "not saved" state, without queueing bills locally.

### Where this plan departs from the specification
- The spec says Laravel 11, Vuex-or-Pinia and "paise or DECIMAL"; this plan fixes Laravel 13, Pinia and integer paise (see `teck-stack.txt`, which supersedes the spec).
- The sprint order is changed: Razorpay lifecycle, the credit wallet and the permission-customization UI move out of Sprint 1, and refunds, expenses and cash register move out of Sprint 3, to keep each sprint deliverable.

## 2. Technology decisions

- **Backend:** Laravel 13 on PHP 8.3+
- **Frontend:** Vue 3, Vite, TypeScript, Vuetify 3, Pinia, vue-router
- **Database:** MySQL 8
- **Cache/session/queue:** Redis
- **Jobs:** Laravel Queues with Horizon and Supervisor
- **Auth:** Laravel Sanctum; mobile OTP plus email/password
- **Authorization:** Spatie Laravel Permission with shop/team scoping
- **Messaging:** Meta WhatsApp Cloud API or BSP; MSG91/Textlocal/Fast2SMS for SMS
- **Payments:** Razorpay subscriptions and invoice payment references
- **PDF/print:** dompdf for A4 and HTML/CSS for 58/80 mm thermal printing via the browser print dialog; a small ESC/POS print bridge app is the fallback if Android Chrome printing proves unreliable in the pilot
- **File storage:** S3-compatible object storage (private bucket, signed URLs) for invoice PDFs, shop logos and staff ID-proof uploads; never store these on the app server disk
- **Exports:** Maatwebsite Excel
- **Real-time:** Laravel Reverb, limited to the dashboard live tiles and delivery-status updates in Sprint 6; nothing in the billing path depends on it
- **Mobile:** responsive PWA first; Capacitor Android wrapper is deferred until after PWA validation
- **Hosting:** VPS or AWS Lightsail/EC2, managed MySQL, Redis, Laravel Forge/Ploi

Laravel 13 and related packages must be verified against the team's PHP runtime and provider SDK versions before the first install.

## 3. Repository structure

Use a **feature/module-first modular monolith** rather than either a strict generic MVC layout or early microservices. Laravel conventions remain inside each bounded context.

```text
/backend
  app/
    Shared/
      Http/Middleware/
      Support/
      Exceptions/
      Policies/
      Providers/
    Tenancy/
      Models/
      Middleware/
      Actions/
      Tests/
    Subscriptions/
      Models/
      Actions/
      Http/
      Jobs/
    Shops/
      Models/
      Actions/
      Http/
      Policies/
    Catalog/
      Models/
      Services/
      Actions/
      Http/
    Staff/
      Models/
      Actions/
      Services/
      Http/
      Jobs/
    Commission/
      Models/
      Services/
      Jobs/
      Actions/
    Billing/
      Models/
      Actions/
      Services/
      Http/
      Jobs/
      Policies/
    Customers/
      Models/
      Actions/
      Services/
      Http/
    Messaging/
      Models/
      Actions/
      Services/
      Jobs/
      Http/
    Reporting/
      Actions/
      Services/
      Jobs/
      Http/
    Audit/
      Models/
      Services/
      Jobs/
  database/
    migrations/
    seeders/
    factories/
  routes/
    api.php
  tests/
    Feature/
      Tenancy/
      Billing/
      Commission/
      Messaging/
      Reporting/
    Unit/
/frontend
  src/
    shared/
      api/
      components/
      composables/
      types/
    auth/
    shops/
    catalog/
    billing/
    customers/
    staff/
    messaging/
    reports/
    router/
    stores/
  public/
/docs
/PLAN.md
/docker-compose.yml
/.github/workflows/
```

Each feature owns its models, API resources, requests, actions/services, jobs, and tests. Keep route definitions versioned under `/api/v1` and expose typed frontend API modules instead of coupling Vue components directly to HTTP calls.

## 3.1 Architectural boundaries

- **Tenant isolation and shop context** belong in `Tenancy` plus shared middleware. Every tenant-aware feature depends on tenant context rather than reimplementing isolation.
- **Billing** must expose application actions such as `CreateInvoice`, `RefundInvoice`, and `CollectDuePayment`; controllers and Vue components must not contain the transaction logic.
- **Commission** owns rule precedence and snapshots. Billing calls the commission service when an invoice is saved and never edits historical commission results through a later rule change.
- **Messaging** owns template, campaign, queue, throttle, retry, and provider webhook behavior. Provider SDK usage must stay inside this module.
- **Reporting** reads shared data and owns nightly aggregate jobs. Report jobs must not become synchronous invoice dependencies.
- **Audit** receives domain events or is called by explicit sensitive actions. It must not become a dependency that every model imports directly.

Use Laravel service providers and contracts only for true shared infrastructure. Avoid a large `Services` directory where unrelated use cases are mixed together. A module may call a public action in another module, but modules must not read each other's database tables or reach into private implementation folders.

## 3.2 Scalability approach

Start as a **modular monolith** deployed with one Laravel application:

1. Scale Laravel and queue workers independently when traffic grows.
2. Use Redis for cache, sessions, locks, and rate limiting.
3. Use Horizon workers for messaging, PDFs, exports, webhooks, and report aggregates.
4. Add MySQL indexes and read replicas only after query measurements identify the need.
5. Split a feature into a deployable service only if measured limits, independent scaling, or organizational ownership justify the operational cost.

Do not create microservices for the MVP. Messaging, billing, commission, CRM, reporting, and appointment features can remain modules in one codebase with explicit boundaries and asynchronous integration.

The frontend follows the same feature approach. Shared UI primitives, API transport, authentication, and the shop-context store are centralized, while business workflows remain under feature folders.

## 4. Cross-cutting foundations

### Sprint 0 — project setup and architecture

**Delivery:** 3–4 days

1. Initialize Laravel and Vue projects with environment, linting, formatting, and test runners.
2. Create Docker Compose services for PHP, MySQL, Redis, Mailpit, and Horizon when local development needs containers.
3. Configure Sanctum, CORS, Caching, queues, logging, timezone, and health endpoints.
4. Establish tenant context and `X-Shop-Id` middleware.
5. Create `BelongsToTenant` global scope and a base `tenant_id`/soft-delete convention.
6. Define permissions and roles: Super Admin, Shop Owner, Manager, Cashier/Receptionist, Staff. Decide up front how Spatie teams map to shops: Manager/Cashier/Staff roles are assigned per shop team, while Shop Owner is a tenant-level role that implicitly covers all shops (do not create one role row per shop for owners). Cashier and Staff must not receive commission-rate permissions.
7. Add feature-test helpers for tenant A/tenant B isolation and shop access.
8. Seed default plans and a 40–50 service onboarding template.

**Exit criteria:**
- Clean local setup.
- CI runs backend and frontend checks.
- Tenant and cross-shop API calls are denied or scoped correctly.
- Unknown and mismatched shop IDs fail with no data leakage.

### Mandatory implementation rules

- Every business table has `tenant_id` (including child tables such as `invoice_items`, `payments` and `invoice_item_staff`, so the global scope and tenant tests apply uniformly), plus `shop_id` where the data is shop-scoped, `created_at`, `updated_at`, and `deleted_at`. Ledger-style tables (payments, audit logs, credit transactions) are append-only and are not soft-deleted.
- Store money as integer paise (`BIGINT`) in every column and in a single `Money` value object; format to ₹ 1,23,456 only at the API/UI edge. Percentages and rates use `DECIMAL(5,2)`. Never use floating-point arithmetic for totals. This supersedes the spec's `DECIMAL(12,2)`.
- Store timestamps in UTC and calculate report cutoff using the shop timezone.
- Use database transactions for invoice save, refund, payment, and invoice-number allocation.
- Lock `shop_counters` with `SELECT ... FOR UPDATE` and allocate sequential invoice numbers per shop and financial year.
- Make saved invoices immutable. Billing revisions create a new revision/audit entry rather than mutating history.
- Capture bill-time rule snapshots and commission outcomes on invoice-item staff rows.
- Enforce plan limits in middleware/service rules and also validate at mutation APIs.
- Mask customer phone numbers for Cashier exports.
- Never place WhatsApp/SMS/Razorpay/PDF work in the synchronous invoice request path.
- Log bill edit, refund, discount override, price change, payout, and permission-sensitive changes.

## 5. Data model implementation order

Use migrations in dependency order rather than creating all tables at once. The specification's table list is incomplete; also add `otp_codes`, `shop_counters`, `invoice_revisions`, `idempotency_keys`, `coupon_redemptions`, `customer_feedback`, `customer_consents` (or consent columns with captured-at dates), `daily_shop_summary`, `daily_staff_summary`, and `webhook_events` (for deduplication). Add a nullable `appointment_id` on invoices from day one.

1. Plans, tenants, users, shops, shop counters, shop-user assignments.
2. Roles/permissions and shop team assignments.
3. Service categories, services, shop prices, products, stock.
4. Staff, shop staff, attendance, advances, commission rules, payouts.
5. Customers, tags, consents, and computed CRM fields.
6. Invoice drafts, invoices, invoice items, item-staff splits, payments, tips, revisions.
7. Expenses, cash registers, and daily aggregates.
8. Message templates, campaigns, campaign messages, automations, coupons, credit wallet.
9. Webhooks, audit logs, and provider IDs.
10. Phase 2: appointments, loyalty, memberships/wallet, and offline queue metadata.

Indexes must include the specification's tenant/mobile, shop/invoice number, shop/created-at, and staff/created-at paths. Add foreign keys and soft-delete indexes conservatively after workload testing.

## 6. Six-sprint MVP build plan

### Sprint 1 — Auth, tenancy, shops, roles

- OTP send/verify and email/password login.
- Owner onboarding wizard: business, first shop, template services, staff, bill format.
- Shop CRUD, settings, opening hours, GST settings, invoice prefix, rounding, timezone.
- Shop switcher, session/store state, `X-Shop-Id`, and owner All Shops context.
- Seeded subscription plans, trial dates, and plan limit checks on shops/staff (enforced in the API).
- Permission model and API with default role grants. The owner-facing customization UI moves to Sprint 6.
- Razorpay subscription lifecycle and the message credit wallet move to Sprint 5, when they are first needed.

### Sprint 2 — Catalog and staff foundations

- Category drag-sort, services, shop price override, products, stock, and combo bundles.
- CSV template, bulk import validation, export, and default service seeding.
- Staff profile, shop assignment, status, bank/UPI fields, and attendance.
- Staff daily/monthly view and role-scoped self view.
- Commission rules and priority engine: staff+service, service, staff, shop default.
- Commission rule snapshots and flat/fixed/slab rule support, plus the shop setting for commission before/after discount and before/after GST (default: after discount, before GST).
- Product-sale commission and tips handling (tips go 100% to staff and are excluded from shop revenue).

### Sprint 3 — Billing/POS

- Fast customer search and quick-add.
- Category/search catalog, shop prices, last-used worker, and item staff split.
- Per-line and whole-bill discounts, coupon validation, role limits, GST/rounding.
- Cash, UPI, card, wallet/credit, split payments, due/credit collection.
- Atomic invoice save and sequential invoice numbers.
- Tips per worker, due/credit bills with part payment, and a "collect due" action.
- Hold/draft bills and bill history/search.
- A4 PDF and 58/80 mm print layouts. Invoice sharing in this sprint is a public invoice link opened via a `wa.me` share; provider-sent WhatsApp/SMS arrives with Sprint 5.
- Edit-after-save as a revision with reason and audit entry (Manager or above).

**Gate:** complete an end-to-end POS pilot: add customer → add 3 lines → assign staff → split discount/payment → print/share.

### Sprint 4 — Staff commission and customers

- Refund/cancel transaction (full or partial), manager PIN, reason, audit log, and commission reversal.
- Expense entry and cash-register open/close with expected vs counted cash. Pilot gate adds: close cash.
- Define the `daily_shop_summary` and `daily_staff_summary` tables and start the nightly jobs now so Sprint 6 reports have real history.
- Automated revenue, counts, average bill, and customer contribution views.
- Payout preview and finalization for fixed salary, commission, tips, advances, and deductions.
- Paid-period lock and A4/WhatsApp payout export.
- Monthly attendance sheet and nightly staff summary.
- Customer profile, visit timeline, dues, consent, tags, source, and segment computation.
- CRM search/filter, CSV import duplicate merge, export, and opt-out enforcement.

**Gate:** a pilot worker statement and historical invoice remain unchanged after commission rule edits.

### Sprint 5 — Messaging and scheduled operations

- Razorpay subscription lifecycle (trial, upgrade, failure/grace) and the message credit wallet with top-up and usage history.
- Provider-sent WhatsApp/SMS invoice delivery replacing the Sprint 3 share link.
- Feedback link after a bill (rating 1–5) and low-rating manager alert.
- Template CRUD, variables, preview, and approval/status fields.
- Campaign audience estimate, channel selection, credit estimate, scheduling, and send.
- Queue throttling/retries/delivery webhooks for WhatsApp/SMS.
- Consent filtering and permanent opt-out exclusion.
- Thank-you, birthday/anniversary, due, feedback, and win-back automations.
- Coupon usage limits and bill redemption.
- Campaign stats and 7-day attributed revenue.

### Sprint 6 — Dashboard, reports, hardening, and pilot

- Dashboard KPI/live queries and 100k-invoice performance paths.
- Sales/P&L/staff/service/payment/GST/expense/customer/campaign reports.
- Nightly `daily_shop_summary` and `daily_staff_summary` jobs.
- Excel/PDF exports and report filters.
- Owner-facing permission customization UI (e.g. cashier discount limit).
- Reverb live dashboard tiles.
- Privacy workflow: owner-requested customer data export and deletion (DPDP Act 2023).
- Uptime, backup, audit, privacy, and security review.
- Seed pilot tenants, imports, and sample reports.
- Mobile, thermal print, PWA install, and weak-network smoke tests.
- Fix P1/P2 findings and prepare production deployment.

**Gate:** owner and manager can operate the pilot without spreadsheet reconciliation; p95 invoice save and dashboard latency meet acceptance targets.

### Scope check against the specification's roadmap diagram

The PDF's release-roadmap diagram (page 16) lists Phase 1 (weeks 1–12) as: sign-up/shops/roles, service catalog, billing/POS/print/WhatsApp bill, staff and attendance, commission and payouts, customer CRM, WhatsApp/SMS campaigns, dashboard and core reports. It places **coupons and feedback, birthday and win-back flows, GST and P&L reports, and retail inventory in Phase 2**, even though the module text describes them. This plan keeps coupons, feedback, automations and GST/P&L in Sprints 5–6 because the module text and acceptance checklist reference them, but treats them as **trim candidates, in this order**: win-back/birthday automations, feedback and low-rating alerts, coupon redemption, P&L report, GST report. Retail inventory stays limited to optional stock counts. Confirm this scope decision before Sprint 5 starts.

The architecture diagram (page 12) shows two client surfaces on one API: an owner/manager web app and a staff/cashier tablet PWA, plus object storage and Horizon/scheduler workers. Both surfaces are one Vue codebase in this plan, with route-level layouts (desk layout vs counter layout).

### Risks and lead-time items (start in week 1)

- **WhatsApp:** Meta Business verification, sender number, and template approval take days to weeks. Apply during Sprint 0 and pick Cloud API vs a BSP by end of Sprint 1.
- **SMS DLT (India):** entity, header (sender ID), and template registration are mandatory before any SMS goes out. Start in week 1.
- **Razorpay:** business KYC and subscription plan setup gate Sprint 5; begin early with test mode.
- **Schedule:** 12 weeks leaves no slack beyond the 3–4 day Sprint 0. Treat Sprint 5 messaging breadth (win-back, due, birthday automations) as the first thing to trim if Sprint 3–4 slip.
- **Sprint 3 is the critical path:** do not start Sprint 5 integrations until the POS pilot gate passes.

## 7. API and UI implementation conventions

- Central API response and validation error format.
- Typed TypeScript API modules and Pinia stores.
- One shop context service; all shop-scoped calls add `X-Shop-Id`.
- POS use a composable for draft state and validation, not a large monolithic component.
- Server-side pagination, searching, sorting, and filtering.
- Idempotency keys for invoice save and payment/refund operations.
- Webhook signature verification and provider event deduplication.
- Queue payloads must contain IDs and tenant context, not mutable business copies.

## 8. Testing strategy

### Backend
- Feature tests for auth, permissions, cross-tenant isolation, cross-shop access, and plan limits.
- Concurrency test for two simultaneous bill saves without duplicate invoice numbers.
- Billing unit tests for GST modes, inclusive/exclusive tax, discounts, split payments, rounding, refund, and due balance.
- Commission tests for each rule precedence, splits, bill-time snapshot, and slab true-up.
- CRM segment and consent tests.
- Queue and webhook tests.
- Database and migration reversibility tests.

### Frontend
- Component tests for bill totals and POS validation.
- Store tests for shop switching and role permissions.
- End-to-end tests for onboarding and main counter flow.
- Accessibility checks for keyboard and 360 px phone layouts.

### Performance and security
- Invoice save p95 below 300 ms.
- Dashboard p95 below 1.5 seconds for 100k invoices per tenant.
- SQL query review for N+1 and missing tenant/shop indexes.
- OTP, login, invoice, webhook, Razorpay, and messaging rate limits.
- Security review of file upload, encrypted bank/UPI fields, export, and audit endpoints.

## 9. Operations, observability, and release

- Laravel Forge/Ploi deployment, blue/green or atomic symlink releases.
- Environment-secret management; never commit `.env` or provider credentials.
- Structured application logs with tenant, shop, user, request ID, and action context.
- Database daily backup with 30-day retention; test restore before pilot.
- Horizon/Supervisor health monitoring and queue retry/dead-letter alerts.
- Sentry or equivalent error monitoring.
- Nightly aggregate and scheduler health alerts.
- 99.5% availability target for the pilot deployment.

## 10. Pilot and MVP sign-off checklist

**Gate 1 (end of Phase 1, per the roadmap):** MVP is live in 3 pilot salons. **Gate 2 (end of Phase 2):** pilot feedback is closed. Phase 2 does not start until Gate 1 passes.

- [ ] One owner can manage 2+ shops and report across all shops.
- [ ] Cross-tenant and cross-shop authorization tests pass.
- [ ] Sequential invoice numbers have no gaps under concurrent billing.
- [ ] Bill, refund, payout, and report calculations reconcile in paise.
- [ ] 3-service customer bill is usable in under 30 seconds.
- [ ] Thermal print and invoice sharing work on Android Chrome.
- [ ] WhatsApp/SMS opt-in and opt-out are enforced.
- [ ] Campaign and automate jobs retry and report provider status.
- [ ] Backups restore successfully.
- [ ] Privacy/deletion/export request workflow exists.
- [ ] Owner and staff have verified PWA/PIN access and role-scoped views.

## 11. Explicitly outside MVP

- Offline IndexedDB bill synchronization.
- Public appointment booking and walk-in queue display.
- Inventory reorder and purchase accounting beyond optional catalog stock tracking.
- Loyalty points and prepaid membership/wallet.
- Customer mobile app.
- Full payroll, beyond advance/deduction/fixed salary in payout statements.
- Email campaigns and unsubscribe handling.
- Android wrapper/Capacitor app, unless PWA pilot requirements reveal a blocker.

## 12. Phase 2 and 3 sequence

Aligned with the specification's roadmap diagram: Phase 2 is weeks 13–20, Phase 3 starts after week 20.

### Phase 2 (weeks 13–20)
1. Appointments: public booking page, staff calendar, reminders, rescheduling, no-shows, queue/tokens.
2. Birthday and win-back flows, coupons and feedback, for any item trimmed from the MVP.
3. Loyalty points and prepaid membership/wallet.
4. Offline bill draft queue and synchronization.
5. Retail inventory (stock, reorder), and GST and P&L reports if trimmed from the MVP.
6. Email campaigns with unsubscribe handling.

### Phase 3 (after week 20)
1. Customer mobile app and a staff mobile app (Capacitor wrapper if the PWA is a blocker).
2. Full payroll.
3. Hindi and regional languages via vue-i18n.
4. Tally/accounting export.
5. Franchise / multi-brand support.
6. Public API and integrations.
7. Advanced fraud/risk controls and data-region options if required.
