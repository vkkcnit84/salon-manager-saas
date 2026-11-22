<?php

namespace Tests\Feature\Messaging;

use App\Messaging\Models\CampaignMessage;
use App\Messaging\Models\MessageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MakesTenants;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use MakesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTenants();
    }

    private function template(): int
    {
        return $this->postJson('/api/v1/marketing/templates', ['channel' => 'whatsapp', 'name' => 'offer_one', 'category' => 'marketing', 'body' => 'Hi {{1}}, {{3}} at {{2}}! Valid till {{4}}.'])->assertCreated()->json('id');
    }

    public function test_campaign_only_reaches_consented_customers_and_debits_credits(): void
    {
        $o = $this->owner('9000000401', credits: 100);
        $this->customer($o, 'Yes', '9811000001');
        $this->customer($o, 'NoConsent', '9811000002', false);
        $opted = $this->customer($o, 'OptedOut', '9811000003');
        $opted->update(['opted_out_at' => now()]);
        Sanctum::actingAs($o);

        $est = $this->postJson('/api/v1/marketing/campaigns/estimate', ['channel' => 'whatsapp', 'audience' => []])->assertOk();
        $this->assertSame(1, $est->json('audience'));

        $tpl = $this->template();
        $c = $this->postJson('/api/v1/marketing/campaigns', ['name' => 'Diwali', 'channel' => 'whatsapp', 'template_id' => $tpl, 'meta' => ['offer' => '20% off', 'expiry' => '30 Nov']])->assertCreated()->json();
        $this->postJson("/api/v1/marketing/campaigns/{$c['id']}/send")->assertOk()->assertJsonPath('status', 'completed');

        $this->assertSame(1, CampaignMessage::withoutGlobalScopes()->count());
        $log = MessageLog::withoutGlobalScopes()->where('direction', 'out')->get();
        $this->assertCount(1, $log);
        $this->assertSame('9811000001', $log[0]->to);
        $this->assertStringContainsString('Hi Yes, 20% off at', $log[0]->body);
        $this->getJson('/api/v1/credits')->assertJsonPath('balance', 99);
        $this->postJson("/api/v1/marketing/campaigns/{$c['id']}/send")->assertStatus(422); // cannot send twice
    }

    public function test_send_blocked_without_enough_credits_and_topup_works(): void
    {
        $o = $this->owner('9000000402', credits: 0);
        $this->customer($o, 'A', '9811000011');
        Sanctum::actingAs($o);
        $c = $this->postJson('/api/v1/marketing/campaigns', ['name' => 'X', 'channel' => 'whatsapp', 'template_id' => $this->template()])->json();
        $this->postJson("/api/v1/marketing/campaigns/{$c['id']}/send")->assertStatus(402);

        $this->postJson('/api/v1/credits/topup', ['pack' => 'p500'])->assertCreated()->assertJsonPath('balance', 500);
        $this->getJson('/api/v1/credits')->assertJsonPath('ledger.0.type', 'topup');
        $this->postJson("/api/v1/marketing/campaigns/{$c['id']}/send")->assertOk();
    }

    public function test_failed_send_is_refunded(): void
    {
        $o = $this->owner('9000000403', credits: 10);
        $this->customer($o, 'BadNumber', '12345'); // gateway rejects: not a valid mobile
        Sanctum::actingAs($o);
        $c = $this->postJson('/api/v1/marketing/campaigns', ['name' => 'X', 'channel' => 'whatsapp', 'template_id' => $this->template()])->json();
        $this->postJson("/api/v1/marketing/campaigns/{$c['id']}/send")->assertOk();
        $this->getJson('/api/v1/credits')->assertJsonPath('balance', 10);
        $this->assertSame('failed', CampaignMessage::withoutGlobalScopes()->first()->status);
    }

    public function test_cashier_cannot_manage_marketing_and_tenants_are_isolated(): void
    {
        $a = $this->owner('9000000404');
        Sanctum::actingAs($a);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $this->template();
        $cashier = $this->member($a, '9000000405', 'cashier', $shop['id']);

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/marketing/templates', ['X-Shop-Id' => $shop['id']])->assertForbidden();

        Sanctum::actingAs($this->owner('9000000406'));
        $this->getJson('/api/v1/marketing/templates')->assertOk()->assertJsonCount(0);
    }

    public function test_webhook_requires_signature_handles_status_and_stop(): void
    {
        $o = $this->owner('9000000407');
        $cust = $this->customer($o, 'Anu', '9811000021');
        $this->inTenant($o, fn () => CampaignMessage::unguarded(fn () => null));
        $body = json_encode(['entry' => [['changes' => [['value' => ['messages' => [['from' => '919811000021', 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]]);
        $sig = 'sha256='.hash_hmac('sha256', $body, config('messaging.whatsapp.app_secret'));

        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertForbidden();
        $this->assertNull($cust->fresh()->opted_out_at);
        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $sig], $body)->assertOk();
        $this->assertNotNull($cust->fresh()->opted_out_at);

        $this->get('/api/v1/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=local-verify-token&hub_challenge=abc123')->assertOk()->assertSee('abc123');
        $this->get('/api/v1/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=abc123')->assertForbidden();
    }

    public function test_coupon_applies_at_pos_and_respects_usage_limit(): void
    {
        Sanctum::actingAs($this->owner('9000000408'));
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Cut', 'price_paise' => 10000, 'gst_bps' => 0])->json();
        $this->postJson('/api/v1/marketing/coupons', ['code' => 'save10', 'type' => 'percent', 'value' => 1000, 'usage_limit' => 1])->assertCreated();
        $h = ['X-Shop-Id' => $shop['id']];
        $bill = ['items' => [['type' => 'service', 'id' => $svc['id']]], 'pay_in_full' => 'cash', 'coupon_code' => 'save10'];

        $this->postJson('/api/v1/invoices', $bill, $h)->assertCreated()->assertJsonPath('total_paise', 9000);
        $this->postJson('/api/v1/invoices', $bill, $h)->assertStatus(422); // limit of 1 reached
        $this->postJson('/api/v1/invoices', ['coupon_code' => 'nope'] + $bill, $h)->assertStatus(422);
    }

    public function test_automation_requires_template_and_birthday_runs_once(): void
    {
        $o = $this->owner('9000000409', credits: 50);
        Sanctum::actingAs($o);
        $tpl = $this->template();
        $this->putJson('/api/v1/marketing/automations/birthday', ['active' => true])->assertStatus(422);
        $this->putJson('/api/v1/marketing/automations/birthday', ['active' => true, 'template_id' => $tpl])->assertOk();

        $c = $this->customer($o, 'Bday', '9811000031');
        $c->update(['birthday' => now('Asia/Kolkata')->subYears(30)->toDateString()]);
        $run = fn () => array_sum(app(\App\Messaging\Services\AutomationRunner::class)->runAll());
        $this->assertSame(1, $run());
        $this->assertSame(0, $run()); // deduped for the year
    }

    public function test_customer_consent_is_recorded_and_opt_out_stops_estimates(): void
    {
        Sanctum::actingAs($this->owner('9000000410'));
        $c = $this->postJson('/api/v1/customers', ['name' => 'Asha', 'mobile' => '9811000041', 'consent_whatsapp' => true, 'birthday' => '1990-05-10'])->assertCreated()->json();
        $this->assertNotNull($c['consent_at']);
        $this->assertSame(1, $this->postJson('/api/v1/marketing/campaigns/estimate', ['channel' => 'whatsapp'])->json('audience'));

        $this->putJson("/api/v1/customers/{$c['id']}", ['opted_out' => true])->assertOk();
        $this->assertSame(0, $this->postJson('/api/v1/marketing/campaigns/estimate', ['channel' => 'whatsapp'])->json('audience'));
        $this->putJson("/api/v1/customers/{$c['id']}", ['opted_out' => false])->assertOk();
        $this->assertSame(1, $this->postJson('/api/v1/marketing/campaigns/estimate', ['channel' => 'whatsapp'])->json('audience'));
    }
}
