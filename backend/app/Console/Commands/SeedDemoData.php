<?php

namespace App\Console\Commands;

use App\Billing\Services\CreateInvoice;
use App\Catalog\Models\Product;
use App\Catalog\Models\Service;
use App\Catalog\Models\ServiceCategory;
use App\Customers\Models\Customer;
use App\Models\User;
use App\Shops\Models\Shop;
use App\Staff\Models\Staff;
use App\Tenancy\Actions\CreateTenantForOwner;
use App\Shops\Actions\CreateShop;
use App\Tenancy\Models\Plan;
use App\Tenancy\Models\Tenant;
use App\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Builds three believable salons (different plans, sizes and teams) plus a platform super admin.
 * Idempotent per owner email: an owner that already exists is skipped. All passwords: Password@123.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--days=60} {--topup : only add bills for days since the last invoice}';

    protected $description = 'Create demo super admin, salons, teams, catalog, customers and historical invoices';

    private const PASSWORD = 'Password@123';

    private const SERVICES = [
        'Hair' => [['Haircut (Men)', 30000, 30], ['Haircut (Women)', 60000, 45], ['Hair Spa', 120000, 60], ['Global Hair Colour', 250000, 120], ['Keratin Treatment', 450000, 150], ['Beard Trim & Style', 15000, 15]],
        'Skin' => [['Fruit Facial', 80000, 45], ['De-Tan Cleanup', 50000, 30], ['Gold Facial', 150000, 60]],
        'Nails' => [['Classic Manicure', 40000, 40], ['Classic Pedicure', 55000, 45]],
        'Bridal' => [['Bridal Makeup', 1200000, 180], ['Party Makeup', 300000, 75]],
        'Wellness' => [['Head Massage', 25000, 20]],
    ];

    private const PRODUCTS = [['Loreal Shampoo 250ml', 45000], ['Hair Serum 100ml', 65000], ['Kama Ayurveda Face Wash', 30000], ['Cold-Pressed Hair Oil', 28000]];

    private const FIRST = ['Anita', 'Rahul', 'Sneha', 'Vikram', 'Pooja', 'Amit', 'Neha', 'Karan', 'Divya', 'Rohit', 'Kavya', 'Manish', 'Isha', 'Sanjay', 'Ritu', 'Deepak', 'Swati', 'Nikhil', 'Tanvi', 'Gaurav', 'Aarti', 'Harsh', 'Simran', 'Varun', 'Lakshmi', 'Arvind', 'Nisha', 'Tarun', 'Bhavna', 'Yash'];

    private const LAST = ['Sharma', 'Verma', 'Singh', 'Gupta', 'Mehta', 'Reddy', 'Iyer', 'Nair', 'Patel', 'Joshi', 'Kulkarni', 'Das'];

    private function profiles(): array
    {
        return [
            [
                'owner' => ['Meera Kapoor', 'meera@urbanglow.test', '9811100001'], 'business' => 'Urban Glow Unisex Salon', 'plan' => 'pro', 'status' => 'active', 'scale' => 1.0, 'seed' => 11,
                'shops' => [
                    ['name' => 'Urban Glow – Indiranagar', 'prefix' => 'UGI', 'address' => '12, 100 Feet Road, Indiranagar, Bengaluru 560038', 'phone' => '+918041234567', 'gstin' => '29ABCPK1234F1Z5', 'tax' => 'exclusive'],
                    ['name' => 'Urban Glow – Koramangala', 'prefix' => 'UGK', 'address' => '5th Block, Koramangala, Bengaluru 560095', 'phone' => '+918045678901', 'gstin' => '29ABCPK1234F1Z5', 'tax' => 'exclusive'],
                ],
                'team' => [['Suresh Rao', 'suresh@urbanglow.test', '9811100002', 'manager', 0], ['Anita Das', 'anita@urbanglow.test', '9811100003', 'cashier', 0], ['Farah Khan', 'farah@urbanglow.test', '9811100004', 'cashier', 1]],
                'staff' => [['Ravi Kumar', 'Senior Stylist', 1200], ['Priya Nair', 'Beautician', 1000], ['Arjun Singh', 'Barber', 800], ['Meera Joshi', 'Makeup Artist', 1500], ['Kiran Patel', 'Nail Artist', 1000]],
            ],
            [
                'owner' => ['Imran Khan', 'imran@royalcuts.test', '9822200001'], 'business' => 'Royal Cuts Barbershop', 'plan' => 'basic', 'status' => 'active', 'scale' => 0.7, 'seed' => 22,
                'shops' => [['name' => 'Royal Cuts – Bandra', 'prefix' => 'RC', 'address' => 'Hill Road, Bandra West, Mumbai 400050', 'phone' => '+912226001122', 'gstin' => '27AAKPK5678L1Z2', 'tax' => 'inclusive']],
                'team' => [['Salman Ali', 'salman@royalcuts.test', '9822200002', 'cashier', 0]],
                'staff' => [['Imran Khan', 'Master Barber', 1500], ['Zaid Ansari', 'Barber', 1000], ['Rehan Shaikh', 'Barber', 800]],
            ],
            [
                'owner' => ['Pooja Malhotra', 'pooja@bellabeauty.test', '9833300001'], 'business' => 'Bella Beauty Studio', 'plan' => 'free', 'status' => 'trial', 'scale' => 0.35, 'seed' => 33, 'days' => 18,
                'shops' => [['name' => 'Bella Beauty Studio', 'prefix' => 'BB', 'address' => 'Sector 18, Noida, UP 201301', 'phone' => '+911204567890', 'gstin' => null, 'tax' => 'exclusive']],
                'team' => [],
                'staff' => [['Pooja Malhotra', 'Owner & Stylist', 0], ['Sana Mirza', 'Beautician', 1000]],
            ],
        ];
    }

    public function handle(CreateInvoice $create): int
    {
        $this->call('admin:create', ['email' => 'admin@salon.test', 'password' => 'Admin@12345', '--name' => 'Platform Admin']);

        config(['messaging.listeners_enabled' => false]); // no per-bill messages while back-filling history
        if ($this->option('topup')) {
            foreach ($this->profiles() as $p) {
                $this->info("{$p['business']}: +".$this->topup($p, $create).' invoices');
            }
            foreach (\App\Tenancy\Models\Tenant::pluck('id') as $tid) {
                app(\App\Reports\Services\DailySummaryBuilder::class)->build($tid, \Carbon\CarbonImmutable::now()->subDays(10), \Carbon\CarbonImmutable::now());
            }

            return 0;
        }
        foreach ($this->profiles() as $p) {
            $owner = User::where('email', $p['owner'][1])->first();
            if ($owner) {
                $this->line("{$p['business']}: already exists, enriching only");
            } else {
                $n = $this->seedTenant($p, $create, (int) ($p['days'] ?? $this->option('days')));
                $this->info("{$p['business']}: {$n} invoices");
                $owner = User::where('email', $p['owner'][1])->first();
            }
            $this->enrich($owner->tenant_id, $p);
        }
        foreach (\App\Tenancy\Models\Tenant::pluck('id') as $tid) {
            app(\App\Reports\Services\DailySummaryBuilder::class)->build($tid, \Carbon\CarbonImmutable::now()->subDays(120), \Carbon\CarbonImmutable::now());
        }
        $this->info('Daily summaries built.');

        return 0;
    }

    private function seedTenant(array $p, CreateInvoice $create, int $days): int
    {
        mt_srand($p['seed']);
        $owner = User::create(['name' => $p['owner'][0], 'email' => $p['owner'][1], 'mobile' => $p['owner'][2], 'password' => self::PASSWORD]);
        $tenant = (new CreateTenantForOwner)($owner, $p['business']);
        $tenant->update(['plan_id' => Plan::where('code', $p['plan'])->value('id'), 'status' => $p['status'], 'onboarding_completed_at' => now(), 'trial_ends_at' => $p['status'] === 'trial' ? now()->addDays(9) : null]);
        $ctx = app(TenantContext::class);
        $ctx->set($tenant->id);

        $shops = collect($p['shops'])->map(fn ($s) => app(CreateShop::class)($tenant->fresh(), ['name' => $s['name'], 'invoice_prefix' => $s['prefix'], 'address' => $s['address'], 'phone' => $s['phone'], 'gstin' => $s['gstin'], 'tax_mode' => $s['tax']]));

        foreach ($p['team'] as [$name, $email, $mobile, $role, $shopIdx]) {
            $u = User::create(['tenant_id' => $tenant->id, 'name' => $name, 'email' => $email, 'mobile' => $mobile, 'password' => self::PASSWORD]);
            DB::table('shop_user')->insert(['tenant_id' => $tenant->id, 'shop_id' => $shops[$shopIdx]->id, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            setPermissionsTeamId($shops[$shopIdx]->id);
            $u->syncRoles([Role::findByName($role, 'web')]);
        }

        $services = collect();
        $order = 0;
        foreach (self::SERVICES as $cat => $list) {
            $c = ServiceCategory::create(['name' => $cat, 'sort_order' => $order++]);
            foreach ($list as [$name, $price, $mins]) {
                $services->push(Service::create(['category_id' => $c->id, 'name' => $name, 'price_paise' => $price, 'duration_min' => $mins, 'gst_bps' => 1800]));
            }
        }
        $products = collect(self::PRODUCTS)->map(fn ($x) => Product::create(['name' => $x[0], 'price_paise' => $x[1], 'gst_bps' => 1800, 'stock' => 400]));
        $staff = collect($p['staff'])->map(function ($s) use ($shops) {
            $m = Staff::create(['name' => $s[0], 'title' => $s[1], 'commission_type' => 'percent', 'commission_value' => $s[2]]);
            $m->shops()->attach($shops->mapWithKeys(fn ($sh) => [$sh->id => ['tenant_id' => $sh->tenant_id]])->all());

            return $m;
        });
        $customers = collect(range(0, 29))->map(fn ($i) => Customer::create(['name' => self::FIRST[$i].' '.self::LAST[mt_rand(0, 11)], 'mobile' => (string) (9700000000 + $p['seed'] * 1000000 + $i * 1373)]));

        $count = $this->generate($p, $create, $owner, $shops, $services, $products, $staff, $customers, $days, 0);
        $ctx->clear();

        return $count;
    }


    /** Creates realistic bills for each day from $fromDaysAgo down to $toDaysAgo (0 = today). Today's bills stop at the current hour. */
    private function generate(array $p, CreateInvoice $create, User $owner, $shops, $services, $products, $staff, $customers, int $fromDaysAgo, int $toDaysAgo): int
    {
        $days = $fromDaysAgo;
        $count = 0;
        foreach ($shops as $shop) {
            $ownerId = $owner->id;
            for ($d = $fromDaysAgo; $d >= $toDaysAgo; $d--) {
                $date = now('Asia/Kolkata')->subDays($d);
                $n = (int) round(max(1, ($date->isWeekend() ? 11 : 6) * $p['scale'] + mt_rand(-1, 3) + intdiv(60 - $d, 15)));
                if ($d === 0) {
                    $n = max(1, (int) round($n * min(1, max(0, now('Asia/Kolkata')->hour - 9) / 11)));
                }
                for ($b = 0; $b < $n; $b++) {
                    $lines = [];
                    foreach ($services->random(mt_rand(1, 3)) as $svc) {
                        $lines[] = ['type' => 'service', 'id' => $svc->id, 'staff_id' => $staff->random()->id];
                    }
                    if (mt_rand(1, 6) === 1) {
                        $lines[] = ['type' => 'product', 'id' => $products->random()->id];
                    }
                    $cust = mt_rand(1, 10) > 3 ? $customers->random() : null;
                    $inv = $create($shop, $ownerId, [
                        'customer_id' => $cust?->id, 'items' => $lines,
                        'discount' => mt_rand(1, 8) === 1 ? ['type' => 'percent', 'value' => 1000] : null,
                        'tip' => mt_rand(1, 5) === 1 ? 5000 : 0,
                        'pay_in_full' => ['cash', 'upi', 'upi', 'card'][mt_rand(0, 3)],
                    ]);
                    $hour = $d === 0 ? mt_rand(10, max(10, now('Asia/Kolkata')->hour - 1)) : mt_rand(10, 20);
                    $when = $date->setTime(min($hour, 20), mt_rand(0, 59))->utc();
                    if ($d === 0 && $when->isFuture()) {
                        $when = now()->subMinutes(mt_rand(5, 120));
                    }
                    DB::table('invoices')->where('id', $inv->id)->update(['created_at' => $when, 'updated_at' => $when]);
                    if ($cust && $d < 20 && mt_rand(1, 12) === 1) { // partly paid: feeds the dues report
                        $half = intdiv($inv->total_paise, 200) * 100;
                        DB::table('payments')->where('invoice_id', $inv->id)->update(['amount_paise' => $half]);
                        DB::table('invoices')->where('id', $inv->id)->update(['status' => 'due', 'paid_paise' => $half, 'due_paise' => $inv->total_paise - $half]);
                    }
                    $count++;
                }
            }
        }

        return $count;
    }

    /** Appends bills for every day since the last invoice up to now, so the demo always shows live-looking numbers. */
    private function topup(array $p, CreateInvoice $create): int
    {
        $owner = User::where('email', $p['owner'][1])->first();
        if (! $owner?->tenant_id) {
            return 0;
        }
        $ctx = app(TenantContext::class);
        $ctx->set($owner->tenant_id);
        $last = DB::table('invoices')->where('tenant_id', $owner->tenant_id)->max('created_at');
        $gap = $last ? max(0, \Carbon\CarbonImmutable::parse($last)->setTimezone('Asia/Kolkata')->startOfDay()->diffInDays(now('Asia/Kolkata')->startOfDay())) : 0;
        mt_srand(crc32($p['business'].now()->toDateString()));
        $n = 0;
        if ($gap > 0) {
            $shops = Shop::all();
            $n = $this->generate($p, $create, $owner, $shops, Service::all(), Product::all(), Staff::all(), Customer::all(), $gap - 1 + ($gap > 0 ? 0 : 0), 0);
        }
        $ctx->clear();

        return $n;
    }

    /** Messaging, consent, expenses, credits and past campaigns. Skips anything that already exists. */
    private function enrich(int $tenantId, array $p): void
    {
        $ctx = app(TenantContext::class);
        $ctx->set($tenantId);
        mt_srand($p['seed'] + 7);
        $tenant = \App\Tenancy\Models\Tenant::find($tenantId);

        if (! \App\Messaging\Models\MessageTemplate::exists()) {
            foreach ([
                ['winback_offer', 'marketing', 'Hi {{1}}! We miss you at {{2}} 💇 {{3}}. Valid till {{4}}. Reply STOP to opt out.'],
                ['festive_offer', 'marketing', 'Hello {{1}}, {{2}} brings you a festive treat: {{3}}. Book before {{4}}! Reply STOP to opt out.'],
                ['birthday_wishes', 'marketing', 'Happy Birthday {{1}}! 🎂 Enjoy a special treat on us at {{2}}.'],
                ['thank_you_bill', 'utility', 'Thanks for visiting {{2}}, {{1}}! Your bill {{3}} for {{4}} is ready. See you again soon.'],
                ['due_reminder', 'utility', 'Hi {{1}}, a gentle reminder of a pending balance at {{2}}. Thank you!'],
            ] as [$name, $cat, $body]) {
                \App\Messaging\Models\MessageTemplate::create(['channel' => 'whatsapp', 'name' => $name, 'category' => $cat, 'body' => $body, 'variables' => ['name', 'shop', 'offer', 'expiry'], 'status' => 'approved', 'provider_template_id' => 'local_'.$name]);
            }
        }
        $tpl = fn ($n) => \App\Messaging\Models\MessageTemplate::where('name', $n)->value('id');
        foreach (['invoice_created' => ['thank_you_bill', true], 'birthday' => ['birthday_wishes', true], 'winback' => ['winback_offer', false], 'due_reminder' => ['due_reminder', false]] as $trigger => [$name, $on]) {
            \App\Messaging\Models\Automation::firstOrCreate(['trigger' => $trigger], ['template_id' => $tpl($name), 'active' => $on]);
        }

        // consent, birthdays, opt-outs (deterministic)
        $customers = Customer::orderBy('id')->get();
        if ($customers->whereNotNull('consent_at')->isEmpty()) {
            foreach ($customers as $i => $c) {
                $c->update([
                    'consent_whatsapp' => $i % 4 !== 3, 'consent_at' => now()->subDays(mt_rand(30, 200)), 'source' => ['walk-in', 'whatsapp_qr', 'referral', 'instagram'][$i % 4],
                    'birthday' => $i % 7 === 0 ? now('Asia/Kolkata')->subYears(mt_rand(22, 45))->setMonth(now('Asia/Kolkata')->month)->setDay(min(28, now('Asia/Kolkata')->day + ($i % 5)))->toDateString() : null,
                    'opted_out_at' => $i % 13 === 12 ? now()->subDays(12) : null,
                ]);
            }
        }

        foreach (Staff::whereNull('mobile')->orderBy('id')->limit(2)->get() as $i => $s) {
            $s->update(['mobile' => (string) (9830000000 + $p['seed'] * 10 + $i)]);
        }
        $owner = User::where('tenant_id', $tenantId)->where('is_owner', true)->first();
        $owner?->forceFill(['bot_prefs' => ['nightly' => true, 'nightly_time' => '22:00']])->save();

        if (! \App\Credits\Models\CreditTransaction::exists()) {
            $credits = match ($p['plan']) { 'pro' => 2000, 'basic' => 500, default => 40 };
            app(\App\Credits\Services\Credits::class)->add($tenantId, $credits, 'grant', 'welcome-pack');
        }

        if (! \App\Messaging\Models\Coupon::exists()) {
            \App\Messaging\Models\Coupon::create(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 1000, 'usage_limit' => 100]);
            \App\Messaging\Models\Coupon::create(['code' => 'FLAT100', 'type' => 'flat', 'value' => 10000, 'usage_limit' => 50, 'expires_at' => now()->addDays(30)]);
        }

        if (! \App\Expenses\Models\Expense::exists()) {
            foreach (Shop::all() as $shop) {
                foreach ([0, 1, 2] as $m) {
                    $month = now('Asia/Kolkata')->subMonths($m)->startOfMonth();
                    foreach ([['Rent', 4500000], ['Salaries', 9000000], ['Electricity', 620000 + mt_rand(0, 90000)], ['Products & supplies', 780000 + mt_rand(0, 150000)], ['Marketing', 250000]] as [$cat, $amt]) {
                        \App\Expenses\Models\Expense::create(['shop_id' => $shop->id, 'category' => $cat, 'amount_paise' => (int) ($amt * ($p['scale'] <= 0.4 ? 0.3 : ($p['scale'] < 1 ? 0.6 : 1))), 'spent_on' => $month->addDays(min(27, 2 + count([$cat]) + mt_rand(0, 5)))->min(now('Asia/Kolkata'))->toDateString(), 'note' => null]);
                    }
                }
            }
        }

        if (! \App\Messaging\Models\Campaign::exists() && $p['plan'] !== 'free') {
            $svc = app(\App\Messaging\Services\CampaignService::class);
            foreach ([[$tpl('festive_offer'), 'Diwali Glow Offer', 'Flat 20% off on Hair Spa', 24, []], [$tpl('festive_offer'), 'Loyal Customer Treat', 'Free head massage with any service', 10, ['min_visits' => 4]]] as [$t, $name, $offer, $daysAgo, $filter]) {
                $c = \App\Messaging\Models\Campaign::create(['name' => $name, 'template_id' => $t, 'channel' => 'whatsapp', 'audience' => $filter, 'meta' => ['offer' => $offer, 'expiry' => now()->subDays($daysAgo - 7)->format('d M')], 'status' => 'draft']);
                $svc->send($c);
                $when = now()->subDays($daysAgo);
                $c->update(['sent_at' => $when, 'created_at' => $when]);
                DB::table('campaign_messages')->where('campaign_id', $c->id)->update(['sent_at' => $when]);
            }
        }
        $ctx->clear();
    }
}
