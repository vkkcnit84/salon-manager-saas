<?php

namespace Tests\Feature\Messaging;

use App\Assistant\Models\AiUsage;
use App\Bot\Models\WaitingEntry;
use App\Bot\Services\BotRouter;
use App\Bot\Services\Pusher;
use App\Customers\Models\Customer;
use App\Messaging\Models\Campaign;
use App\Messaging\Models\MessageLog;
use App\Staff\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MakesTenants;
use Tests\TestCase;

class BotAndAssistantTest extends TestCase
{
    use MakesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTenants();
        Cache::flush();
    }

    /** owner with one shop, two stylists called Ravi/Rahul and a Priya, and a few bills */
    private function salon(string $mobile = '9822000001'): array
    {
        $o = $this->owner($mobile, credits: 500);
        Sanctum::actingAs($o);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'Glow Main'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Haircut', 'price_paise' => 50000, 'gst_bps' => 0])->json();
        $ravi = $this->postJson('/api/v1/staff', ['name' => 'Ravi Kumar', 'mobile' => '9830000001', 'commission_value' => 1000, 'shop_ids' => [$shop['id']]])->json();
        $priya = $this->postJson('/api/v1/staff', ['name' => 'Priya Nair', 'commission_value' => 1000, 'shop_ids' => [$shop['id']]])->json();
        $h = ['X-Shop-Id' => $shop['id']];
        foreach ([[$ravi, 2], [$priya, 1]] as [$st, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $this->postJson('/api/v1/invoices', ['items' => [['type' => 'service', 'id' => $svc['id'], 'staff_id' => $st['id']]], 'pay_in_full' => 'upi'], $h)->assertCreated();
            }
        }

        return [$o, $shop, $svc, $ravi, $priya];
    }

    public function test_owner_menu_and_sales_by_whatsapp(): void
    {
        [$o] = $this->salon();
        Sanctum::actingAs($o);
        $menu = $this->postJson('/api/v1/bot/simulate', ['from' => '9822000001', 'text' => 'menu'])->assertOk()->json('replies.0');
        $this->assertCount(6, $menu['buttons']);
        // Regression: a natural-language commission question must not be hijacked by the "month" shortcut.
        $q = $this->postJson('/api/v1/bot/simulate', ['from' => '9822000001', 'text' => 'Is mahine Ravi ka commission kitna hua?'])->json('replies.0.text');
        $this->assertStringContainsString('Ravi Kumar ka commission', $q);
        $this->assertStringNotContainsString('This month:', $q);
        $today = $this->postJson('/api/v1/bot/simulate', ['from' => '9822000001', 'button' => 'm:today'])->json('replies.0.text');
        $this->assertStringContainsString('3 bills', $today);
        $this->assertStringContainsString('₹ 1,500.00', $today);
    }

    public function test_button_permission_is_checked_for_managers(): void
    {
        [$o, $shop] = $this->salon('9822000002');
        $tpl = $this->postJson('/api/v1/marketing/templates', ['channel' => 'whatsapp', 'name' => 'wb', 'category' => 'marketing', 'body' => 'Hi {{1}} {{3}}'])->json('id');
        $draft = $this->inTenant($o, fn () => Campaign::create(['name' => 'D', 'template_id' => $tpl, 'channel' => 'whatsapp', 'audience' => [], 'status' => 'draft']));
        $mgr = $this->member($o, '9822000099', 'manager', $shop['id']);

        $r = app(BotRouter::class)->handle('9822000099', '', "winback:{$draft->id}:send");
        $this->assertStringContainsString("don't have permission", $r[0]['text']);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bot.denied', 'user_id' => $mgr->id]);

        $r = app(BotRouter::class)->handle('9822000002', '', "winback:{$draft->id}:send"); // owner
        $this->assertStringContainsString('Sent to', $r[0]['text']);
        $this->assertNotSame('draft', $draft->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bot.campaign_sent']);
    }

    public function test_qr_checkin_flow_creates_customer_consent_and_queue(): void
    {
        [$o, $shop] = $this->salon('9822000003');
        $bot = app(BotRouter::class);
        $code = 'CHECKIN-'.$shop['code'];

        $this->assertStringContainsString('name', $bot->handle('9876501234', $code)[0]['text']);
        $consent = $bot->handle('9876501234', 'Sneha Rao')[0];
        $this->assertSame('consent:yes', $consent['buttons'][0]['id']);
        $done = $bot->handle('9876501234', '', 'consent:yes')[0]['text'];
        $this->assertStringContainsString('#1', $done);

        $c = Customer::withoutGlobalScopes()->where('mobile', '9876501234')->first();
        $this->assertSame('Sneha Rao', $c->name);
        $this->assertTrue($c->consent_whatsapp);
        $this->assertSame(1, WaitingEntry::withoutGlobalScopes()->where('status', 'waiting')->count());

        Sanctum::actingAs($o);
        $this->getJson('/api/v1/waiting', ['X-Shop-Id' => $shop['id']])->assertOk()->assertJsonPath('0.customer.name', 'Sneha Rao');

        $bot->handle('9876501234', 'STOP');
        $this->assertNotNull($c->fresh()->opted_out_at);
    }

    public function test_staff_message_is_batched_to_one_per_window_and_24h_window_rule(): void
    {
        [$o, $shop, $svc, $ravi] = $this->salon('9822000004');
        // salon() already billed Ravi twice: only the first bill may have produced a message in the window.
        $this->assertSame(1, MessageLog::withoutGlobalScopes()->where('to', '9830000001')->where('direction', 'out')->count());

        $pusher = app(Pusher::class);
        $this->assertFalse($pusher->windowOpen('9822000004'));
        $this->inTenant($o, fn () => $pusher->push($o->tenant_id, '9822000004', 'Summary', 'summary'));
        $this->assertSame('owner_daily_summary', MessageLog::withoutGlobalScopes()->where('to', '9822000004')->latest('id')->value('template'));

        app(BotRouter::class)->handle('9822000004', 'hi');           // inbound opens the 24h window
        $this->inTenant($o, fn () => $pusher->push($o->tenant_id, '9822000004', 'Summary 2', 'summary'));
        $this->assertNull(MessageLog::withoutGlobalScopes()->where('to', '9822000004')->where('direction', 'out')->latest('id')->value('template'));
    }

    public function test_assistant_answers_hinglish_stays_in_tenant_and_asks_followups(): void
    {
        [$o] = $this->salon('9822000005');
        $other = $this->owner('9822000006');
        Sanctum::actingAs($o);

        $r = $this->postJson('/api/v1/assistant/ask', ['question' => 'Is mahine Ravi ka commission kitna hua?'])->assertOk();
        $this->assertSame('get_commission', $r->json('tool'));
        $this->assertSame('hinglish', $r->json('language'));
        $this->assertStringContainsString('₹ 100.00', $r->json('answer'));   // 2 bills x ₹500 x 10%
        $this->assertStringContainsString('this month', $r->json('answer'));

        $this->postJson('/api/v1/assistant/ask', ['question' => 'commission kitna hua?'])->assertJsonPath('tool', 'get_commission')->assertSee('Which staff');
        $this->postJson('/api/v1/assistant/ask', ['question' => 'what is the meaning of life'])->assertJsonPath('answer', "I don't have that data.");

        $this->inTenant($o, fn () => Staff::create(['name' => 'Ravi Sharma', 'commission_value' => 500]));
        $this->postJson('/api/v1/assistant/ask', ['question' => 'Ravi ki commission?'])->assertSee('more than one match');

        Sanctum::actingAs($other); // another tenant never sees these numbers
        $this->assertStringContainsString('₹ 0.00', $this->postJson('/api/v1/assistant/ask', ['question' => 'aaj ki sales kitni hui'])->assertOk()->json('answer'));
        $this->postJson('/api/v1/assistant/ask', ['question' => 'Ravi commission'])->assertSee('Which staff');
    }

    public function test_assistant_drafts_only_never_sends_caches_and_enforces_quota(): void
    {
        [$o] = $this->salon('9822000007');
        $this->postJson('/api/v1/marketing/templates', ['channel' => 'whatsapp', 'name' => 'wb2', 'category' => 'marketing', 'body' => 'Hi {{1}} {{3}}']);
        $this->customer($o, 'Idle', '9811000051');
        $before = MessageLog::withoutGlobalScopes()->where('to', '9811000051')->count();

        $r = $this->postJson('/api/v1/assistant/ask', ['question' => 'send winback offer to inactive customers'])->assertOk();
        $this->assertSame('draft_winback_campaign', $r->json('tool'));
        $this->assertCount(2, $r->json('buttons'));
        $this->assertSame($before, MessageLog::withoutGlobalScopes()->where('to', '9811000051')->count()); // nothing sent
        $this->assertSame('draft', Campaign::withoutGlobalScopes()->latest('id')->first()->status);

        $this->postJson('/api/v1/assistant/ask', ['question' => 'aaj ki sales'])->assertJsonPath('cached', false);
        $this->postJson('/api/v1/assistant/ask', ['question' => 'aaj ki sales'])->assertJsonPath('cached', true);
        $this->assertSame(1, AiUsage::withoutGlobalScopes()->where('cached', true)->count());

        config(['messaging.ai.monthly_quota.pro' => 2]);
        $this->postJson('/api/v1/assistant/ask', ['question' => 'dues kitne hai'])->assertStatus(429);
    }

    public function test_voice_endpoint_uses_transcript_and_rejects_empty(): void
    {
        [$o] = $this->salon('9822000008');
        Sanctum::actingAs($o);
        $this->postJson('/api/v1/assistant/voice', ['transcript' => 'आज की बिक्री कितनी हुई'])->assertOk()->assertJsonPath('language', 'hi')->assertJsonPath('tool', 'get_sales');
        $this->postJson('/api/v1/assistant/voice', [])->assertStatus(422);
    }
}
