# Salon Management SaaS — Feature Specification

> Source document: `Salon Management SaaS – Feature Specification.pdf`  
> Document date: November 17, 2026  
> Prepared for: Vinay Kumar Singh

## 1. Overview

The product is a multi-tenant SaaS web application with a mobile-friendly PWA. Salon owners can manage one or more shops, billing, staff commission, customers, promotions, and earnings in one system.

### Goals

- Complete a customer bill at the counter in under 30 seconds.
- Automatically calculate worker service counts, revenue, commission, and earnings.
- Build a tenant-wide customer database with visit history and WhatsApp/SMS campaigns.
- Give owners a daily, monthly, and per-shop view of sales, expenses, and net earnings.

### Target users

- Small and mid-size salons
- Barber shops
- Unisex parlours
- Spas
- Owners operating 1–20 shops
- Initial market: India, with INR, GST, and WhatsApp-first workflows

### MVP scope

- Shops
- Staff
- Services and products
- Billing/POS
- Commission
- Customers/CRM
- Promotions
- Reports

### Later phases

- Online appointment booking
- Inventory
- Memberships and packages
- Customer application
- Payroll

## 2. Roles and permissions

Permissions are scoped first by tenant and then by shop. Use Spatie Laravel Permission with a shop as the permission team.

| Role | Typical access |
|---|---|
| Super Admin | Manage tenants, plans, usage, support, and global SMS/WhatsApp settings |
| Shop Owner | Manage everything across all owned shops, including staff, pricing, campaigns, reports, and settings |
| Manager | Manage assigned shops, billing, customers, staff attendance, and daily reports; no payout or plan settings |
| Cashier / Receptionist | Create bills, add customers, and view today's sales; cannot delete bills or view commission rates |
| Staff — Barber / Stylist | Optional own login; view own services, commission, and earnings only |

### Permission rules

- The owner can switch shops or view an owner-only **All shops** report.
- Permissions are individual toggles. For example, an owner may allow a cashier to give discounts up to 10%.
- Editing or deleting a saved bill requires Manager role or higher plus a reason.
- Every edit or deletion must create an audit-log entry.

## 3. Module 1 — Tenants and shops

### Features

1. Owner signs up once and can add shops allowed by their plan.
2. Sign-up uses mobile OTP or email/password.
3. Onboarding wizard collects:
   - Business name
   - First shop
   - Default services
   - Initial staff
   - Bill format
4. Shop settings include:
   - Name and address
   - GSTIN
   - Phone
   - Logo
   - Opening hours
   - Weekly off
   - Invoice prefix
   - Currency
   - Time zone
5. Shop switcher stores the active shop in session or frontend state.
6. Every API call uses `X-Shop-Id` for shop-scoped operations.
7. Subscription plans: **Free**, **Basic**, and **Pro**.
8. Plan limits cover shops, staff, and monthly message credits.
9. Razorpay subscriptions support trials such as 14 days.
10. Shop settings include tax mode, rounding, default commission rule, bill footer, WhatsApp sender, and low-credit alert threshold.
11. Each tenant has a message-credit wallet with top-ups and usage history.

### Acceptance criteria

- A tenant cannot read or write another tenant's data.
- Invoice numbers are sequential per shop and financial year.
- Concurrent invoices must not create duplicate invoice numbers or gaps.
- Shop limits are enforced by the API, not only by the UI.

## 4. Module 2 — Catalog

The catalog belongs to the tenant and is selectable while billing.

### Service categories

- Hair
- Beard
- Facial
- Spa
- Colour
- Bridal
- Custom categories
- Drag-to-sort ordering

### Services

Each service has:

- Name
- Category
- Base price
- Duration in minutes
- Gender: male, female, or unisex
- GST rate
- Active status
- Optional commission override

### Shop-level prices

A service may have different prices by shop. For example, a haircut may cost ₹150 in one shop and ₹200 in another.

### Retail products

MVP-optional fields include:

- Name
- SKU
- MRP
- Selling price
- Stock quantity
- Sale commission

Stock is reduced whenever a product is billed.

### Other catalog features

- Service combos/packages at fixed prices
- 40–50 default onboarding services
- CSV import/export

## 5. Module 3 — Staff and commission

Every invoice line is associated with a worker. This link drives service counts, worker revenue, and commission.

### Staff profile

- Name and mobile
- Photo
- Designation: barber, stylist, beautician, helper, or custom
- Assigned shops
- Joining date
- ID proof upload
- Bank/UPI details
- Fixed salary, commission-only, or fixed plus commission
- Active/inactive status

Inactive staff keep historical records but cannot be selected on new bills.

### Attendance

- Staff or manager check-in/check-out
- Leave marking
- Monthly attendance sheet
- Staff and manager views

### Work tracking

Automatically calculated from bills:

- Services completed by day, week, and month
- Revenue generated
- Average bill value
- Customers served
- Two-worker service splits by percentage

### Commission rules

Rules are checked in this order. The first match wins:

1. Staff + service
2. Service
3. Staff
4. Shop default

| Rule type | Example |
|---|---|
| Flat percentage | 40% of each service |
| Fixed amount | ₹30 per haircut |
| Monthly tier/slab | 30% through ₹20,000, then 40% |
| Product commission | 10% of retail sales |
| Tips | 100% to worker |

Commission settings determine whether calculations occur before or after discount and before or after GST. The default is **after discount and before GST**.

The selected rule and rate are stored as a snapshot on each invoice line. Changing a rule later must not rewrite historical earnings.

### Payouts

A payout statement covers:

- Services completed
- Gross revenue
- Commission
- Tips
- Advances
- Deductions
- Fixed salary
- Net payable

The system must support advances/deductions and mark payout periods as paid by cash, UPI, or bank. Paid periods are locked. Statements can be exported as PDF and shared by WhatsApp.

Staff can see today's service count, monthly earnings, and pending payout from a mobile screen.

## 6. Module 4 — Billing/POS

The POS must be tablet-friendly, use few taps, and support weak-network counter operations.

### Billing flow

1. Search customer by mobile number.
2. Quickly add unknown customer using name and mobile.
3. Add services or products by category tile or search.
4. Use the shop price and remember the last-used worker.
5. Assign one or more workers to each line.
6. Split multi-worker lines by percentage.
7. Apply line or whole-bill discount as percentage or fixed amount.
8. Validate coupon and role discount limits.
9. Calculate GST with inclusive/exclusive settings and CGST/SGST for GSTIN billing.
10. Accept cash, UPI, card, wallet/credit, or split payments.
11. Save the invoice, then print or send the invoice link.

### POS features

- Held bills/drafts
- Tips per worker
- Due/credit bills
- Partial payments
- Collect-due workflow
- Full or partial refund/cancellation with reason
- Manager PIN for refund/cancellation
- Automatic commission reversal
- Bill history with date, shop, worker, payment mode, and status filters
- Reprint and resend
- Cash-register opening, expected cash, counted cash, variance, and closing report
- Expense entry
- Offline bill queueing in IndexedDB — Phase 2

### Acceptance criteria

- A 3-service bill for a known customer is completable in 30 seconds or less.
- Totals equal `sum(lines) - discount + tax`, using integer paise or exact decimal fields.
- Saved bills are immutable.
- Corrections create revisions and audit-log entries.
- GST calculations support inclusive/exclusive tax and rounding settings.

## 7. Module 5 — Customer management

Customers belong to the tenant. A customer is shared across all of the owner's shops.

### Customer profile

- Name
- Mobile — unique per tenant
- Alternate number
- Email
- Gender
- Date of birth
- Anniversary
- Address/area
- Notes
- Preferred stylist
- Tags
- Source: walk-in, Instagram, referral, Google, or custom
- SMS/WhatsApp/email consent flags and consent date
- Opt-out status

### Auto-computed fields

- First visit
- Last visit
- Total visits
- Total spent
- Average bill
- Favourite service
- Due balance

### Segments

- **New:** 1 visit
- **Regular:** 3+ visits in 90 days
- **At risk:** no visit for 45+ days
- **Lost:** no visit for 90+ days

All day thresholds must be configurable.

### CRM features

- Search by name or mobile
- Filter by tag, segment, last visit, spend, birthday month, and shop
- Visit timeline with bills, services, workers, payments, dues, and messages
- CSV/Excel import with mobile duplicate merge
- Owner-only CSV export
- Post-bill feedback rating
- Low-rating manager alerts

### Later phases

- Loyalty points
- Prepaid membership/wallet

## 8. Module 6 — Promotional messaging

Owners select a customer segment and approved template, then send immediately or schedule delivery. Every message uses tenant credit wallet balance.

### Channels

| Channel | Provider | Compliance |
|---|---|---|
| WhatsApp | Meta WhatsApp Cloud API or a BSP | Pre-approved marketing templates; opt-in required |
| SMS | MSG91, Textlocal, Fast2SMS | DLT entity, sender ID/header, and template ID |
| Email | Amazon SES or Mailgun — Phase 2 | Unsubscribe link |

### Campaign features

- Template variables such as `{{name}}`, `{{shop}}`, `{{offer}}`, and `{{expiry}}`
- Template preview and provider approval status
- Audience filters and live customer count
- Channel and schedule selection
- Credit-cost estimate
- Consent and opt-out enforcement
- Coupon usage limits and expiry
- Queue-based sending with provider rate limits
- Retry handling
- Delivery webhooks
- Sent, delivered, read, failed, reply, and attributed-revenue reports
- 7-day campaign revenue attribution

### Automated triggers

- Thank-you plus invoice after a bill
- Birthday/anniversary offer at 9 AM
- Win-back message after a configurable period without a visit
- Due-payment reminder
- Feedback request two hours after a visit

Message sending and delivery webhooks must not block the POS.

## 9. Module 7 — Dashboard and reports

The owner dashboard answers today's sales and worker performance for one shop or all shops.

### Dashboard filters

- Date range
- One shop or All shops

### Widgets

- Today's sales
- Bill count
- Average bill value
- New customers
- Cash in hand
- Commission payable
- Sales trend versus previous period
- Top services
- Top workers by revenue
- Payment-mode split
- Per-shop sales comparison
- Low-credit alert
- Pending dues
- Today's staff absence

### Reports

| Report | Output |
|---|---|
| Sales summary | Gross sales, discounts, tax, net sales by period/shop |
| Earnings / P&L | Net sales − commission − salary − expenses |
| Staff performance | Service count, revenue, commission, tips, attendance |
| Commission / payout | Payable, paid, and pending by worker/period |
| Service-wise | Count and revenue per service/category |
| Payment mode | Cash, UPI, card, due collected, and day-close variance |
| Customer | New/returning, top spenders, segments, lost customers |
| Discounts/coupons | Discount by staff and coupon |
| GST | Taxable value and CGST/SGST per invoice |
| Expenses | Category and shop |
| Campaign ROI | Credits spent versus attributed revenue |

Reports are filterable and exportable to Excel/PDF.

### Performance design

Use nightly `daily_shop_summary` and `daily_staff_summary` tables. Use live data for today's reports.

## 10. Module 8 — Appointments

Appointments are deferred to Phase 2. The database must allow a nullable `appointment_id` on invoices from day one.

### Planned features

- Staff day/week calendar
- Drag-and-drop slots
- Slot duration based on service duration
- Public shop booking page: `/book/shop-slug`
- Service, stylist, and slot selection
- OTP-verified customer mobile
- Confirmation and two-hour reminder
- Reschedule/cancel links
- Walk-in token display
- One-click appointment-to-bill conversion
- No-show tracking

## 11. Data model

Use one shared MySQL database with `tenant_id` on every tenant-owned business table and a global Eloquent scope.

### Core tables

| Table | Main fields |
|---|---|
| `tenants` | Plan, owner, trial, message credits, status |
| `shops` | Tenant, name, slug, address, GSTIN, invoice prefix, settings |
| `users` | Tenant, name, mobile, email, password, owner flag |
| `shop_user` | Shop, user, role |
| `staff` | Tenant, user, designation, salary type, fixed salary, bank/UPI, status |
| `shop_staff` | Shop and staff assignments |
| `service_categories` | Tenant, name, sort order |
| `services` | Tenant, category, name, price, duration, gender, tax, active status |
| `shop_service_prices` | Shop-specific service price |
| `products` | Tenant, SKU, MRP, selling price, tax |
| `shop_product_stock` | Shop-specific product quantity |
| `commission_rules` | Tenant, shop, staff, service, type, value, slabs, priority |
| `customers` | Tenant, contact details, tags, consent, totals, due balance |
| `invoices` | Tenant, shop, invoice number, customer, totals, paid/due, status, creator |
| `invoice_items` | Invoice, item type/id, name, quantity, price, discount, tax, amount |
| `invoice_item_staff` | Worker split, commission amount, rule snapshot |
| `payments` | Invoice, mode, amount, reference, received time |
| `tips` | Invoice, staff, amount |
| `attendance` | Staff, shop, date, check-in/out, status |
| `staff_advances` | Staff, amount, date, note |
| `payouts` | Staff, period, commission, salary, tips, deductions, net, paid data |
| `expenses` | Shop, category, amount, date, note |
| `cash_registers` | Shop, date, opening/expected/counted amount, closing user |
| `message_templates` | Tenant, channel, body, provider/DLT IDs, status |
| `campaigns` | Tenant, channel, template, filters, schedule, status, stats |
| `campaign_messages` | Campaign/customer delivery status and provider IDs |
| `automations` | Tenant trigger, template, delay, active state |
| `coupons` | Tenant, code, type, value, usage limit, expiry |
| `credit_transactions` | Tenant top-up/debit, credits, reference |
| `audit_logs` | Tenant, user, action, affected model, old/new values, IP |

### Required indexes

- Unique `(tenant_id, mobile)` on customers
- Unique `(shop_id, invoice_no)` on invoices
- `(shop_id, created_at)` on invoices
- `(staff_id, created_at)` on invoice item staff

### Data conventions

- Store money using integer paise or `DECIMAL(12,2)`.
- Do not use floating-point arithmetic for billing totals.
- Every table includes `created_at`, `updated_at`, and `deleted_at`.
- Store timestamps in UTC and render in the shop time zone.
- Use soft deletes for business records.

## 12. Technical architecture

### Stack

- **Backend:** Laravel 13, PHP 8.3+
- **Frontend:** Vue 3, TypeScript, Vite, Vuetify 3
- **State:** Pinia
- **Routing:** Vue Router
- **Database:** MySQL 8
- **Cache/queues:** Redis
- **Queue workers:** Laravel Queues, Horizon, Supervisor
- **Real-time:** Laravel Reverb
- **Authentication:** Laravel Sanctum
- **Authorization:** Spatie Laravel Permission
- **PWA:** vite-plugin-pwa
- **Mobile:** PWA first; Capacitor Android later if required
- **Hosting:** VPS or AWS Lightsail/EC2; managed MySQL/Redis

### Architecture decisions

- Use one REST API for web, PWA, and future Android clients.
- Use `/api/v1` endpoints.
- Implement tenant isolation with a tenant trait/global scope or a single-database tenancy package.
- Require `X-Shop-Id` on shop-scoped requests.
- Use Sanctum bearer authentication.
- Run a `CommissionCalculator` when an invoice is saved.
- Lock `shop_counters` using `SELECT ... FOR UPDATE` inside the invoice transaction.
- Run messaging, PDF generation, and report aggregation asynchronously.
- Use DomPDF for A4 and HTML/CSS for thermal printing.
- Use Maatwebsite Excel for exports.

## 13. API outline

All billing plans and shop-scoped operations use `/api/v1`.

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/otp/send`, `POST /auth/otp/verify`, `POST /auth/login`, `POST /auth/logout`, `GET /me` |
| Shops | CRUD `/shops`, settings update |
| Users and roles | CRUD `/users`, role updates |
| Catalog | CRUD service categories/services/products, shop prices, CSV import |
| Staff | CRUD `/staff`, attendance, performance, commission rules |
| Payouts | Payout preview/create, advances |
| Billing | Invoice create/list/detail/refund/payment/PDF/send, invoice drafts |
| Cash and expenses | Cash open/close, expense CRUD |
| Customers | Customer CRUD/search/timeline/import/export |
| Messaging | Template and campaign CRUD, estimates, send, automation and coupon CRUD |
| Webhooks | WhatsApp, SMS, and Razorpay |
| Reports | Dashboard, report filters, and exports |
| Plans | Plan list, subscription, and credit top-up |

Every shop-scoped route validates `X-Shop-Id` against the user's shop access.

## 14. Non-functional requirements

### Security

- HTTPS only
- OTP rate limiting
- Bcrypt passwords
- Encrypted bank/UPI fields
- Role check on every endpoint
- Signature verification for webhooks
- Audit logs for sensitive operations

### Privacy

- Tenant and shop isolation
- Mask customer phone numbers for Cashier role exports
- Honor marketing opt-outs
- Support owner-requested data export and deletion
- Align with India DPDP Act requirements

### Performance

- Invoice API p95 under 300 ms
- Dashboard p95 under 1.5 seconds for 100k invoices per tenant
- Use indexes and nightly aggregates
- Avoid N+1 queries

### Availability and backup

- 99.5% uptime target
- Supervised Horizon queue workers
- Daily MySQL backups
- 30-day backup retention
- Point-in-time recovery where available

### Device support

- Responsive from 360 px phones to tablets and desktops
- Chrome on Android
- PWA installation
- ESC/POS thermal printing through browser print or a print bridge

### Localization and time

- English MVP
- Hindi and additional languages later through vue-i18n
- INR formatting, for example ₹1,23,456
- UTC database timestamps
- Shop-midnight report boundaries

## 15. Release roadmap

### Sprint 1 — Auth and foundation

- Auth and tenancy
- Shops
- Roles and permissions
- Catalog foundation

### Sprint 2 — Billing foundation

- POS and customer quick-add
- Billing and split payments
- Invoice print/share
- Draft invoices

### Sprint 3 — Staff and commission

- Attendance
- Commission engine
- Payout preview and payment
- Staff self-view

### Sprint 4 — CRM and reports

- Customer profiles and history
- Segments and imports
- Dashboard
- Core reports

### Sprint 5 — Messaging

- Templates and campaigns
- WhatsApp/SMS queues and webhooks
- Automations
- Coupons and credit wallet

### Sprint 6 — Pilot hardening

- Aggregate reports
- Security and privacy review
- Printing/PWA testing
- Pilot deployment and backups

## 16. MVP acceptance gates

- Cross-tenant access tests pass.
- Cross-shop permissions pass.
- Invoice numbering passes concurrent-billing tests.
- A 3-service known-customer bill completes in under 30 seconds.
- Bill totals, GST, due balances, and commission reconcile in paise/decimal values.
- Rule changes do not rewrite past earnings.
- Refund and payout operations reverse or lock results correctly.
- WhatsApp/SMS excludes opted-out customers.
- Reports meet the p95 performance targets.
- Backup restore is tested before pilot launch.

------some new expectation---
Prompt 6 — Messaging, templates, campaigns, automations
Module: Messaging.

1. Contracts: MessageGateway { sendTemplate(to, template, variables, buttons?): SendResult; sendText(to, text) } with implementations WhatsAppCloudGateway (Meta Graph API), Msg91SmsGateway (DLT template id + sender id), LogGateway (local). Config per tenant: own WhatsApp number (WABA id, phone_number_id, token stored encrypted) or the platform's shared number.
2. Migrations + models: message_templates (channel, name, language, category marketing/utility, body with {{1}} placeholders mapped to variables name/shop/offer/expiry, provider_template_id, dlt_template_id, status), campaigns (audience filters JSON from CustomerFilter, template_id, channel, scheduled_at, status, stats JSON), campaign_messages (status queued/sent/delivered/read/failed, provider_msg_id, error), automations (trigger enum invoice_created/birthday/anniversary/winback/due_reminder/feedback, delay, template_id, active), coupons (code, type percent/flat, value, usage_limit, used, expires_at, campaign_id).
3. Template sync: command that pulls WhatsApp template approval status from Meta and updates status.
4. Campaign flow: POST /campaigns/estimate returns audience count (consented, not opted-out only) and credit cost; POST /campaigns/{id}/send dispatches chunked SendCampaignMessageJob batches (Bus::batch), throttled with Redis rate limiting per provider, retries with backoff. Debit message credits per message; stop when credits run out and notify owner.
5. Webhooks: POST /webhooks/whatsapp (verify GET hub.challenge; verify X-Hub-Signature-256 with the app secret; update statuses; route inbound messages to the Bot module), POST /webhooks/sms (delivery reports). Handle STOP / unsubscribe replies by setting opted_out_at.
6. Automations: scheduler runs birthday/anniversary at 9:00 shop time, win-back daily, due reminders weekly; InvoiceCreated listener sends the bill + feedback link after the configured delay.
7. Attribution: campaign report shows sent/delivered/read/failed and revenue from invoices of targeted customers within 7 days.
8. Frontend: template library with preview, campaign builder stepper (Audience → Template → Schedule → Cost → Confirm), campaign report page, automations toggles, coupons CRUD, credits wallet with Razorpay top-up.
9. Tests: consent filtering, credit debit, webhook signature check, opt-out handling, Http::fake for providers.
Prompt 7 — Dashboard and reports
Module: Reports.

1. Aggregate tables daily_shop_summary (shop, date, gross, discount, tax, net, bills, customers_new, customers_returning, cash, upi, card, due_collected, expenses, commission) and daily_staff_summary (staff, shop, date, services_count, revenue, commission, tips). A nightly job BuildDailySummariesJob (shop time zone midnight) plus a rebuild command for a date range. Today's numbers come live from invoices.
2. ReportService with one method per report, all taking (tenant, shop_id or 'all', from, to): salesSummary, profitAndLoss (net sales - commission - salaries - expenses), staffPerformance, commissionPayouts, serviceWise, paymentModes (with day-close differences), customers (new vs returning, top spenders, lost), discounts, gstReport (invoice-wise taxable value, CGST, SGST), expenses, campaignRoi.
3. Endpoints: GET /dashboard (KPI tiles, 30-day trend, top services, top staff, payment split, shop comparison, alerts), GET /reports/{type}, GET /reports/{type}/export?format=xlsx|pdf (queued for big ranges).
4. Frontend: dashboard with three simple cards first (Today, This month, Staff) and a "Send this on WhatsApp" button; charts with vue-echarts; report pages with date range picker, shop selector (All shops for owner), data table, export.
5. Performance: indexes on (shop_id, created_at) and (staff_id, created_at); dashboard must respond under 1.5 s with 100k invoices — add a seeder that creates that volume and a test that measures it.
Prompt 8 — WhatsApp bot (no-dashboard experience)
Module: Bot. Owners, managers and staff should get everything on WhatsApp without opening the app.

1. Inbound routing: the Messaging webhook passes inbound messages to BotRouter. Identify the sender by mobile: owner/manager user, staff, known customer, or unknown. Store bot_sessions (wa_id, actor_type, actor_id, state, context JSON, expires_at) for multi-step flows.
2. Respect WhatsApp rules: free-form replies only inside the 24-hour customer service window; outside it use approved utility templates. Keep template names in config.
3. Owner/manager features:
   - Nightly summary (scheduled 22:00 shop time, utility template): sales per shop, cash vs UPI, bills, top staff, dues, alerts. Opt-in per owner and time configurable.
   - Menu: reply "menu" -> interactive list (Today sales, This month, Staff commission, Dues, Send offer, Help).
   - Interactive button approvals with reply ids, e.g. winback:{campaignDraftId}:send|skip, advance:{id}:approve|reject, invoice_deleted:{id}:view. Verify the button sender has permission before acting; log in audit_logs.
   - Alerts: bill deleted/edited, discount above limit, cash mismatch at day close, no bill by 12:00, low credits.
4. Staff features: after each InvoiceCreated, send each staff on the bill a short utility message: service, amount, today's count and today's commission (batched: max 1 message per 15 minutes per staff to save cost). Reply "earnings" -> this month's earnings and pending payout.
5. Customer QR check-in: generate a per-shop QR for wa.me/<number>?text=CHECKIN-<shopCode>. On receipt: create/find customer by wa_id, ask name (if new) and consent with Yes/No buttons, put them in the shop's waiting queue visible on the POS screen. Customers reply STOP to opt out.
6. WhatsApp Flows (optional): a Flow for owners to add a staff member or a new service from WhatsApp; handle the flow's data exchange endpoint with request decryption as per Meta docs.
7. Cost control: count every business-initiated message against message credits; show bot usage in the credits page.
8. Tests: router identification, permission checks on buttons, 24-hour window logic, batching of staff messages, CHECKIN flow end to end with faked webhooks.
Prompt 9 — AI assistant (text and Hindi voice questions)
Module: Bot/Assistant. Let owners ask questions like "Is mahine Ravi ka commission kitna hua?" by text or voice note on WhatsApp (and in an in-app chat box).

1. Contracts: LlmClient (chat with tool calling) and SpeechToText (transcribe(audioPath, languageHint)). Implement LlmClient with the Laravel AI SDK (or Prism PHP) so the provider (Anthropic Claude, OpenAI or Google Gemini) is a config value. Implement SpeechToText with a provider that supports Hindi and Hinglish (e.g. Sarvam AI, OpenAI transcription, or Google Cloud Speech-to-Text).
2. Safety: the model NEVER writes SQL. Expose only whitelisted tools that call ReportService with the caller's tenant and allowed shops already bound:
   - get_sales(period, shop?) ; get_staff_performance(staff_name?, period) ; get_commission(staff_name, period) ; get_dues(shop?) ; get_top_services(period) ; get_customer_counts(period) ; draft_winback_campaign(days_inactive, offer_text)
   - Staff name matching: fuzzy match within the tenant's staff; ask a follow-up if ambiguous.
   - Actions that send messages or spend credits only create a DRAFT and return Send/Skip buttons; never send directly.
3. System prompt: answer in the user's language (Hindi, Hinglish or English), short (<= 5 lines), INR format with Indian digit grouping, always state the period and shop used, say "I don't have that data" instead of guessing.
4. Voice flow: inbound audio message -> download media via Graph API media URL -> SpeechToText -> same pipeline as text -> reply as text.
5. Cost + limits: per-tenant monthly AI question quota by plan, cache identical questions for 10 minutes, log tokens and cost per tenant in ai_usage table.
6. In-app: a chat panel on the dashboard using the same AssistantService via POST /assistant/ask.
7. Tests: fake LlmClient returning tool calls; verify tenant scoping, ambiguous staff follow-up, draft-only actions, quota enforcement.
Prompt 10 — PWA and Android app (Capacitor)
Module: Mobile. Reuse the Vue app; no native Kotlin code unless a plugin needs config.

1. PWA: add vite-plugin-pwa with manifest (name, short_name, icons 192/512, theme colour, display standalone, start_url /pos), service worker caching the app shell, and an "Install app" prompt banner.
2. Offline billing: when offline, save invoices to IndexedDB (idb-keyval or Dexie) with a client UUID; a sync queue posts them when online; the API treats client_uuid as an idempotency key; show a "3 bills waiting to sync" badge. Invoice numbers are assigned by the server on sync.
3. Capacitor: add @capacitor/core, cli, android; appId com.<brand>.salon; webDir dist. API base URL from env (absolute https URL), Sanctum token stored with @capacitor/preferences, CORS updated in Laravel.
4. Plugins: @capacitor/push-notifications with Firebase Cloud Messaging (store device tokens per user; Laravel sends via FCM HTTP v1), @capacitor/share (send invoice PDF), @capacitor/app (back button), a Bluetooth thermal printer plugin for 58/80mm ESC/POS printing from the POS screen.
5. Build: npm run build && npx cap sync android; document steps to generate a signed AAB in Android Studio and the Play Console closed-testing requirement.
6. Write a docs/MOBILE.md with all commands, keystore handling (never commit it), and versioning (versionCode bump each release).