# Plan — New Expectations (Prompts 6–10)

Covers the block marked "some new expectation" in [FEATURE-SPECIFICATION.md](FEATURE-SPECIFICATION.md) (line 640): **Messaging, Reports, WhatsApp bot, AI assistant, PWA/Android.** It extends [MVP-BUILD-PLAN.md](MVP-BUILD-PLAN.md); strategy and rules stay in [PLAN.md](../PLAN.md).

Team assumption is unchanged: Dev A backend-led, Dev B frontend-led, sprints of 2 weeks. Dates are relative to the start of this plan ("W1"), not calendar dates.

## 0. Build status (all prompts built against local fakes)

All five prompts are implemented end to end with third-party services replaced by local fakes behind interfaces; see [INTEGRATIONS.md](INTEGRATIONS.md) for exactly where each real provider plugs in and [DEMO.md](DEMO.md) for logins. 51 backend tests pass.

| Prompt | Status | Notes |
|---|---|---|
| 7 Reports | **Done** | Summary tables + nightly build exist (`reports:rebuild`), but the dashboard and reports read **live SQL** because a missing or stale summary row would read as "no sales"; the tables are groundwork for very long ranges. 11 report types, CSV/XLSX/PDF export. Dashboard median 0.72 s at 100k invoices (target 1.5 s). Not done: queued exports for very large ranges, salaries as a separate payroll source (P&L reads the "Salaries" expense category). |
| 6 Messaging | **Done (fake providers)** | Gateways, templates, campaigns with credits/consent/refund, webhooks (signed, idempotent), automations, coupons, wallet. Not done: Meta template-sync command, per-tenant WhatsApp credentials, Bus::batch + Redis throttling (needs a queue worker), feedback links and low-rating alerts. |
| 10a PWA | **Done** | Manifest with icons and `/pos` start, install banner, app-shell cache. |
| 8 Bot | **Done (simulator)** | Router, owner/manager/staff/customer flows, approval buttons with permission checks, nightly summary, alerts, staff pings (1 per 15 min), QR check-in + POS waiting list, 24 h window rule. Not done: WhatsApp Flows. |
| 9 AI assistant | **Done (rule-based)** | Whitelisted tools, fuzzy staff match, draft-only actions, quotas, 10-min cache, `ai_usage`, English/Hinglish/Hindi, in-app chat + browser voice. Real LLM and speech provider plug in later. |
| 10b Offline + Android | **Done (build-ready)** | IndexedDB queue with server-side idempotency, Capacitor project, Preferences/back/share/push-registration. Not done: FCM sending, Bluetooth printer plugin, signed AAB (needs Android Studio and your keystore). See [MOBILE.md](MOBILE.md). |

## 1. Summary

| # | Prompt | What it adds | In MVP? | Est. effort | Depends on |
|---|---|---|---|---|---|
| 7 | Dashboard & reports | Summary tables, 11 reports, XLSX/PDF export, perf proof at 100k invoices | **Yes** (upgrade of what exists) | 2 wks | Expenses, payouts for P&L |
| 6 | Messaging, templates, campaigns, automations | Gateways, templates, campaigns, webhooks, credits, coupons, automations | **Yes** (Sprint 5 scope) | 4 wks | Consent fields, Redis queues, Meta/DLT/Razorpay accounts |
| 10a | PWA install | Manifest, icons, install banner, app-shell cache | **Yes** | 3 days | None |
| 10b | Offline billing + Capacitor Android | IndexedDB queue, sync, FCM push, Bluetooth printer, signed AAB | **No — Phase 2** | 3 wks | Stable POS, `client_uuid` idempotency |
| 8 | WhatsApp bot | Owner/staff menus, approvals, nightly summary, QR check-in | **No — Phase 2** | 4 wks | Prompt 6 webhooks and credits, Prompt 7 `ReportService` |
| 9 | AI assistant (text + Hindi voice) | Tool-calling LLM over `ReportService`, speech-to-text, quotas | **No — Phase 3** | 3 wks | Prompts 7 and 8 |

Total new work is roughly 17 developer-weeks of calendar time if run serially, about 11 weeks with both developers working in parallel tracks (see section 6).

## 2. Decisions needed before starting

These are conflicts or gaps between the new block and the existing plan. Each changes the schedule.

1. **Offline billing (Prompt 10.2) contradicts PLAN.md**, which defers it to Phase 2 and tells the POS to rely on fast retries and idempotency keys. *Recommendation:* keep it in Phase 2 (10b). Do only the PWA install in the MVP.
2. **Capacitor Android (10.3) vs "PWA first, wrapper later if needed."** *Recommendation:* start 10b only after the pilot salons confirm that Chrome PWA printing is not good enough. A Bluetooth thermal printer is the usual trigger.
3. **Bot and AI (Prompts 8, 9) are not in the 12-week MVP.** They also multiply WhatsApp cost, because business-initiated messages burn credits. *Recommendation:* Phase 2 for the bot, Phase 3 for the AI assistant, both opt-in per plan.
4. **Charts library:** the spec says `vue-echarts`; the current dashboard uses small hand-written SVG charts with no dependency. *Recommendation:* keep the SVG components for the dashboard (fast, tiny) and add ECharts only if a report needs zoom, stacked or multi-series charts.
5. **WhatsApp route** (Meta Cloud API vs a BSP such as Gupshup/Interakt) is already due by end of Sprint 1 in MVP-BUILD-PLAN. Prompt 6 assumes Meta Graph API directly. Confirm, because the gateway, template sync and webhook code differ.
6. **Shared vs tenant-owned WhatsApp number.** Prompt 6 supports both. Per-tenant numbers need encrypted credential storage and Meta onboarding per salon. *Recommendation:* ship the shared platform number first; add tenant-owned numbers as a Pro-plan option.
7. **LLM and speech providers (Prompt 9)** — pick before Phase 3 (Anthropic/OpenAI/Gemini; Sarvam/OpenAI/Google for Hindi speech). Config-driven, so this is a cost and quality choice, not an architecture one.

## 3. Current state versus what the prompts assume

| Needed by the prompts | Today | Gap |
|---|---|---|
| Reports | Dashboard + 5-tab Reports page, owner-only, aggregated in PHP per request | No summary tables, no P&L / GST / customer / discount / expense / campaign ROI reports, no XLSX/PDF export, no shop comparison, no perf test |
| Customer consent and opt-out | `customers` has name, mobile, notes only | Need consent flags per channel, consent date, `opted_out_at`, birthday, anniversary, source, tags |
| Message credits | `tenants.message_credits` column exists | No `credit_transactions` ledger, no wallet, no Razorpay top-up |
| Events and queues | Queue/cache/session use the database driver | Need Redis, Horizon, scheduler, `job_batches`, an `InvoiceCreated` event |
| Refunds, expenses, day close, payouts | Not built (Sprint 4) | Needed for P&L, bot alerts ("bill deleted", "cash mismatch") and staff payout questions |
| Audit log | Not built | Needed for bot button approvals and edit/delete alerts |
| Invoice PDF + public link | Not built | Needed to send "bill" over WhatsApp |
| Appointments / waiting queue | Not built | QR check-in needs a simple waiting-queue table shown on the POS |
| PWA | `vite-plugin-pwa` installed, manifest has no icons, no `start_url` | Icons, install banner, offline shell |
| Permissions | Only `billing.create` and `customers.manage` enforced | Bot buttons and reports need real permission checks |

## 4. Shared groundwork (do first — about 1 week, parallel to Prompt 7)

Every prompt after 7 leans on these, so they are scheduled once, not per prompt.

- [ ] **Redis + Horizon + scheduler** running locally and in CI; switch queue and cache off the database driver. Add `job_batches` migration.
- [ ] **Customer consent model**: `consent_whatsapp`, `consent_sms`, `consent_at`, `opted_out_at`, `birthday`, `anniversary`, `source`; a single `Customer::contactable($channel)` scope that every sender must use.
- [ ] **`credit_transactions` ledger** (append-only) and a `Credits` service with `debit()` that fails closed at zero.
- [ ] **`audit_logs`** table and an `Audit::record()` helper; wire to existing sensitive actions (price change, role change, tenant suspend).
- [ ] **Domain events**: `InvoiceCreated`, `InvoiceEdited`, `InvoiceDeleted`, `DayClosed`. Listeners are queued.
- [ ] **Customer filter object** (`CustomerFilter`) used by segments, campaigns and the bot's win-back draft.
- [ ] **Permissions pass**: enforce `reports.*`, `commission.view`, `campaigns.manage` on the API; role-aware sidebar so cashiers and managers stop seeing owner-only pages.

## 5. Per-prompt plan

### 5.1 Prompt 7 — Dashboard and reports (W1–W2)

Smallest gap and the foundation for the bot and the AI assistant, so it goes first.

**Backend**
- [ ] Migrations: `daily_shop_summary`, `daily_staff_summary` (unique `(shop_id, date)` / `(staff_id, shop_id, date)`), indexes `(shop_id, created_at)` and `(staff_id, created_at)`.
- [ ] `BuildDailySummariesJob` at shop-local midnight, plus `reports:rebuild --from --to`. Today's numbers stay live from invoices.
- [ ] Refactor the existing `ReportService` to the required signature `(tenant, shop_id|'all', from, to)` with one method per report: `salesSummary`, `profitAndLoss`, `staffPerformance`, `commissionPayouts`, `serviceWise`, `paymentModes`, `customers`, `discounts`, `gstReport`, `expenses`, `campaignRoi`. Reports whose inputs do not exist yet (P&L needs expenses and salaries; campaign ROI needs Prompt 6) return an empty-but-valid shape and are completed when their module lands.
- [ ] `GET /dashboard`, `GET /reports/{type}`, `GET /reports/{type}/export?format=xlsx|pdf`; queue exports above a row threshold and notify when ready (Maatwebsite Excel, DomPDF).
- [ ] Dashboard additions: shop comparison and alerts.
- [ ] `php artisan perf:seed-invoices 100000` and a test asserting the dashboard answers in under 1.5 s.

**Frontend**
- [ ] Three-card home (Today, This month, Staff) with **"Send this on WhatsApp"** (uses `wa.me` until Prompt 6 lands, then the provider).
- [ ] Report pages share one layout: date-range picker, shop selector (All shops for owners), table, export button.
- [ ] Role-aware access: managers see daily reports; cashiers see today only.

**Exit:** each report reconciles to invoice totals in paise; dashboard under 1.5 s at 100k invoices; cross-tenant test on every report endpoint.

### 5.2 Prompt 6 — Messaging, templates, campaigns, automations (W2–W5)

**Gateways (W2)**
- [ ] `MessageGateway` contract with `sendTemplate`, `sendText`; `LogGateway` first so everything is testable locally, then `WhatsAppCloudGateway` and `Msg91SmsGateway`.
- [ ] Per-tenant config with encrypted token storage; platform shared number as the default.

**Data (W2)**
- [ ] Migrations and models: `message_templates`, `campaigns`, `campaign_messages`, `automations`, `coupons` as listed in the prompt.
- [ ] `templates:sync` command pulling Meta approval status; run on a schedule.

**Campaign flow (W3)**
- [ ] `POST /campaigns/estimate` (consented, not opted-out only; credit cost) and `POST /campaigns/{id}/send`.
- [ ] `SendCampaignMessageJob` in `Bus::batch` chunks, Redis rate limit per provider, retries with backoff, credit debit per message, stop and alert the owner when credits run out.
- [ ] Dead-letter handling and a visible failed-message list.

**Webhooks (W3)**
- [ ] `GET/POST /webhooks/whatsapp`: hub challenge, `X-Hub-Signature-256` verification, delivery status updates, inbound messages handed to the Bot module (stub until Prompt 8).
- [ ] `POST /webhooks/sms` delivery reports. `STOP` replies set `opted_out_at`.
- [ ] Idempotent: duplicate provider events do not double-update.

**Automations and attribution (W4)**
- [ ] Scheduler: birthday and anniversary at 09:00 shop time, win-back daily, due reminders weekly; `InvoiceCreated` listener sends bill plus feedback link after the configured delay.
- [ ] Attribution: campaign report shows sent/delivered/read/failed and revenue from targeted customers' invoices within 7 days.
- [ ] Trim order if schedule slips (from PLAN.md): win-back/birthday → feedback → coupons.

**Frontend (W3–W5)**
- [ ] Template library with preview; campaign stepper (Audience → Template → Schedule → Cost → Confirm); campaign report; automation toggles; coupons CRUD; credits wallet with Razorpay top-up.

**Tests:** consent filtering, credit debit, webhook signature, opt-out handling, `Http::fake` for providers.

**Lead-time items to start in W1 (not code):** Meta WhatsApp Business verification and first templates, India SMS DLT registration (entity, header, templates), Razorpay KYC and test plans.

**Exit:** an opted-out customer is never messaged; failed sends retry and surface status; webhooks are idempotent; credits never go negative.

### 5.3 Prompt 10a — PWA install (W2, 3 days)

- [ ] Manifest: name, short name, 192/512 icons (plus maskable), theme colour, `display: standalone`, `start_url: /pos`.
- [ ] Service worker caches the app shell only; API calls are never cached.
- [ ] "Install app" banner using `beforeinstallprompt`, dismissible and remembered.
- [ ] Weak-network behaviour: clear "not saved" state on a failed bill and automatic retry with the same idempotency key.

**Exit:** installable on Android Chrome; Lighthouse PWA checks pass.

### 5.4 Prompt 8 — WhatsApp bot (Phase 2, 4 weeks)

Only starts after Prompt 6 webhooks and credits and Prompt 7 `ReportService` exist.

- [ ] `BotRouter` identifies the sender by mobile (owner/manager, staff, known customer, unknown); `bot_sessions` for multi-step flows.
- [ ] 24-hour window rule: free text inside the window, approved utility templates outside it; template names kept in config.
- [ ] **Owners/managers:** nightly 22:00 summary (opt-in, configurable time), `menu` interactive list, approval buttons (`winback:{id}:send|skip`, `advance:{id}:approve|reject`) that check permission before acting and write to `audit_logs`, alerts (bill deleted/edited, over-limit discount, cash mismatch, no bill by 12:00, low credits).
- [ ] **Staff:** message after each bill (service, amount, today's count and commission), batched to at most one per 15 minutes per staff; `earnings` reply gives month earnings and pending payout.
- [ ] **QR check-in:** per-shop `wa.me/<number>?text=CHECKIN-<shopCode>`; create/find customer, ask name and consent with Yes/No buttons, add to the shop's waiting queue shown on the POS; `STOP` opts out.
- [ ] Optional WhatsApp Flow for adding staff or services (needs Meta request decryption).
- [ ] Cost control: every business-initiated message counts against credits; bot usage shown on the credits page.

**Needs from earlier sprints:** refunds, day close, advances, audit log, waiting-queue table.

**Tests:** router identification, button permission checks, 24-hour window, staff-message batching, full CHECKIN flow with faked webhooks.

### 5.5 Prompt 9 — AI assistant (Phase 3, 3 weeks)

- [ ] `LlmClient` (tool calling) via the Laravel AI SDK or Prism PHP; `SpeechToText` for Hindi/Hinglish. Provider is a config value.
- [ ] **Safety rule:** the model never writes SQL. It only calls whitelisted tools (`get_sales`, `get_staff_performance`, `get_commission`, `get_dues`, `get_top_services`, `get_customer_counts`, `draft_winback_campaign`) with tenant and allowed shops already bound.
- [ ] Fuzzy staff-name match inside the tenant, with a follow-up question when ambiguous.
- [ ] Actions that send messages or spend credits only create a **draft** and return Send/Skip buttons.
- [ ] System prompt: answer in the user's language, at most 5 lines, INR with Indian grouping, always state the period and shop, and say "I don't have that data" instead of guessing.
- [ ] Voice: inbound audio → Graph API media download → speech-to-text → same pipeline → text reply.
- [ ] Quota per plan, 10-minute cache for identical questions, `ai_usage` table logging tokens and cost per tenant.
- [ ] In-app chat panel on the dashboard through `POST /assistant/ask`.

**Tests:** fake `LlmClient` returning tool calls; tenant scoping; ambiguous-staff follow-up; draft-only actions; quota enforcement.

### 5.6 Prompt 10b — Offline billing and Capacitor Android (Phase 2, 3 weeks)

- [ ] IndexedDB queue (Dexie or idb-keyval) with a client UUID per bill; the server treats `client_uuid` as the idempotency key (today's `idempotency_key` already behaves this way, so rename or alias rather than add a second mechanism); "N bills waiting to sync" badge; invoice numbers are assigned by the server on sync, and offline receipts show a provisional reference.
- [ ] Capacitor: `@capacitor/core`, `cli`, `android`; absolute HTTPS API base URL from env; Sanctum token in `@capacitor/preferences`; CORS updated in Laravel.
- [ ] Plugins: push notifications with FCM HTTP v1 (device tokens per user), share (invoice PDF), app (back button), Bluetooth ESC/POS printer for 58/80 mm.
- [ ] `docs/MOBILE.md`: commands, signed AAB steps, keystore handling (never committed), `versionCode` bump per release, Play Console closed-testing requirement.

**Risk:** offline billing and sequential invoice numbers pull in opposite directions. The plan above avoids a gap in the official sequence by numbering on sync, but salons that hand a paper receipt first will need a provisional number printed on it. Decide the receipt wording with a pilot salon before building.

## 6. Schedule

Weeks are relative; both developers work in parallel after W1.

| Week | Dev A (backend) | Dev B (frontend) |
|---|---|---|
| W1 | Shared groundwork (Redis, consent, credits, audit, events); start external account applications | Role-aware sidebar, permissions UI work; PWA install (10a) |
| W2 | Prompt 7 backend: summaries, `ReportService`, exports, perf seeder | Prompt 7 frontend: home cards, report pages |
| W3 | Prompt 6: gateways, data model, template sync | Prompt 6: template library, credits wallet UI |
| W4 | Prompt 6: campaign batching, webhooks, opt-out | Prompt 6: campaign builder stepper |
| W5 | Prompt 6: automations, attribution, coupons | Prompt 6: campaign report, automation toggles, coupons |
| W6 | Hardening: provider failure drills, opt-out audit, load test | Polish; WhatsApp "send this" on dashboard via provider |
| — | **Gate: pilot-ready messaging and reports** | |
| W7–W10 | Prompt 8 bot (Phase 2) | Prompt 8 UI: waiting queue on POS, credits page bot usage |
| W11–W13 | Prompt 10b offline and Capacitor | Prompt 10b offline UI, Android build |
| W14–W16 | Prompt 9 AI assistant (Phase 3) | Chat panel, quota UI |

If Prompts 6 and 7 slip, protect them; Prompts 8–10b are all deferrable without affecting the pilot.

## 7. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Meta template approval or DLT registration is slow | Prompt 6 cannot be tested against real providers | Apply in W1; build against `LogGateway`; keep the interface stable |
| WhatsApp cost grows faster than plan prices | Margin loss, especially with the bot's staff messages | Credit-debit on every send, batching, plan-level quotas, usage on the credits page |
| Sending to opted-out customers | Legal and account-ban risk | One `contactable()` scope used by every sender; test that fails if a sender bypasses it |
| Report queries slow at scale | Dashboard misses the 1.5 s target | Summary tables, required indexes, seeded 100k-invoice test in CI |
| LLM answers wrong numbers | Owner trust | Tool-only access to `ReportService`, "I don't have that data" rule, draft-only actions |
| Bot buttons act without permission | Data or money impact | Re-check permission server-side when a button is pressed; audit-log every action |
| Offline numbering gaps | Invoice sequence compliance | Number on sync only; provisional receipt reference |
| Scope creep from five new modules | MVP slips | Hard rule: nothing from 8, 9, 10b enters the MVP without an explicit cut elsewhere |

## 8. Definition of done (applies to every prompt)

- Tenant and shop scoping on every endpoint, with a cross-tenant test.
- Money stays in paise through `Money`; no float math in reports or credits.
- Anything slow or external runs on the queue with IDs in the payload.
- Every sender uses `Customer::contactable($channel)`; every send debits credits.
- Sensitive actions write `audit_logs`.
- API, typed frontend module and tests land together; works at 360 px.
