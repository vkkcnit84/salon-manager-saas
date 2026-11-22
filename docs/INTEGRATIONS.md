# Third-party integrations — where to plug them in later

Everything external runs on a **local fake** today, behind an interface. To go live you replace one class and set an env var; nothing else in the app changes. No real message is sent, no real payment is taken, and no AI provider is called.

| Integration | Interface (the seam) | Local fake (used now) | Real implementation to write | Switch |
|---|---|---|---|---|
| **WhatsApp** (campaigns, bills, bot replies) | `App\Messaging\Contracts\MessageGateway` | `Gateways\LogGateway` → writes to `message_log` (Marketing ▸ Message log) | `Gateways\WhatsAppCloudGateway` (stub exists): Meta Graph API `POST /{phone_number_id}/messages`, per-tenant encrypted token or the shared platform number | `WHATSAPP_DRIVER=cloud` |
| **SMS** (DLT) | same interface | `LogGateway` | `Gateways\Msg91SmsGateway` (stub exists): MSG91 flow API with DLT template id + sender id | `SMS_DRIVER=msg91` |
| **Delivery receipts / inbound** | `POST /api/v1/webhooks/whatsapp` (+ `GET` verify, `POST /webhooks/sms`) | Fully implemented: `X-Hub-Signature-256` check, forward-only status updates, STOP opt-out, inbound → `BotRouter` | Point Meta's webhook at it; set `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`. In `WebhookController::whatsapp`, send each bot `$reply` back through the gateway (marked with a comment) | env vars |
| **Template approval** | `message_templates.status` | New templates are approved instantly | Create as `pending`; add a `templates:sync` command that polls Meta and DLT and updates `status` | — |
| **Payments** (credit top-up) | `App\Credits\Services\PaymentGateway` | `FakePaymentGateway` settles instantly (`FAKE-PAY-…`) | A Razorpay implementation: create order → checkout → **verify signature in a webhook** before calling `Credits::add()` | `PAYMENT_DRIVER=razorpay` + rebind in `AppServiceProvider` |
| **Subscriptions** | `tenants.plan_id` / `status` | Super admin changes plan and status by hand | Razorpay Subscriptions with webhook dedupe; trial, upgrade, failure grace | — |
| **AI assistant** | `App\Assistant\Contracts\LlmClient` | `RuleBasedLlmClient` (keyword intents, English/Hinglish/Hindi) | A client built on the Laravel AI SDK or Prism PHP (Claude / OpenAI / Gemini). It must only *choose* a tool from `AssistantService::TOOLS`; keep that whitelist. | `AI_DRIVER` + rebind |
| **Speech-to-text** | `App\Assistant\Contracts\SpeechToText` | `FakeSpeechToText` trusts a transcript from the browser's speech recognition | Sarvam / Whisper / Google STT client; for WhatsApp voice notes also download media via the Graph API first | rebind |
| **Push notifications** | `App\Mobile\FcmSender` | Logs only; device tokens are stored (`device_tokens`) | FCM HTTP v1 with a service-account OAuth token | — |
| **Bluetooth printer** | `window.SalonPrinter.write(bytes)` | Falls back to the browser print dialog | A Capacitor Bluetooth plugin that sets `window.SalonPrinter`; ESC/POS bytes come from `src/mobile/escpos.ts` | — |
| **Queues / Redis** | Laravel queue config | `QUEUE_CONNECTION=database`; `MESSAGING_SYNC=true` runs sends **inline in the request** (fine locally, not for production) | Run Redis + Horizon + the scheduler (`php artisan schedule:work`) and set `MESSAGING_SYNC=false`. Campaign sends then use `SendCampaignJob`, and the per-bill thank-you / staff messages use `SendInvoiceMessagesJob`; both bind the tenant from their own payload, not from a web request. Still to add: per-provider rate limiting and `Bus::batch` chunking. | env vars |

> **Not just a class swap for two things.** (1) With `MESSAGING_SYNC=true` provider calls happen inside the request that saves a bill, so a real provider must only be switched on together with a queue worker and `MESSAGING_SYNC=false`. (2) The webhook handler currently logs bot replies locally; with a real provider it must send them back through the gateway (marked in `WebhookController::whatsapp`).

## Rules that must survive the swap
- **Consent:** every customer message goes through `MessageSender::toCustomer()`, which refuses without consent or after opt-out and debits a credit first (refunding on failure). Do not call a gateway directly.
- **Credits** are an append-only ledger (`credit_transactions`); never write `tenants.message_credits` directly.
- **24-hour rule:** `Pusher` uses free text only inside 24 h of the person's last inbound message, otherwise an approved utility template. Keep the template names in `Pusher::TEMPLATES` approved in Meta.
- **Webhooks** must stay signature-verified and idempotent.
- **The assistant** never runs SQL and only creates *drafts* for anything that sends messages or spends credits.

## Environment variables
```
WHATSAPP_DRIVER=log|cloud      SMS_DRIVER=log|msg91
WHATSAPP_APP_SECRET=...        WHATSAPP_VERIFY_TOKEN=...      WHATSAPP_NUMBER=91XXXXXXXXXX
PAYMENT_DRIVER=fake|razorpay   AI_DRIVER=rules
MESSAGING_SYNC=true|false      MESSAGING_LISTENERS=true|false
```
