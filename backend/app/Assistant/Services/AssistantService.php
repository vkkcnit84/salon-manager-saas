<?php

namespace App\Assistant\Services;

use App\Assistant\Contracts\LlmClient;
use App\Assistant\Models\AiUsage;
use App\Customers\Models\Customer;
use App\Messaging\Models\Campaign;
use App\Messaging\Models\MessageTemplate;
use App\Messaging\Services\CampaignService;
use App\Models\User;
use App\Reports\Services\ReportService;
use App\Shops\Models\Shop;
use App\Staff\Models\Staff;
use App\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Safe assistant: the LLM only picks one of the whitelisted tools below; every tool runs through
 * ReportService with the caller's tenant already bound. Message-sending actions only create DRAFTS.
 */
class AssistantService
{
    public const TOOLS = ['get_sales', 'get_staff_performance', 'get_commission', 'get_dues', 'get_top_services', 'get_customer_counts', 'draft_winback_campaign'];

    public function __construct(private LlmClient $llm, private ReportService $reports, private CampaignService $campaigns) {}

    public function ask(User $user, string $question): array
    {
        $question = trim(mb_substr($question, 0, 500));
        abort_if($question === '', 422, 'Ask a question first.');

        $key = 'ai:'.md5($user->tenant_id.'|'.mb_strtolower($question));
        $isDraft = false;
        if ($hit = Cache::get($key)) {
            $this->log($user, $question, $hit['tool'] ?? null, $hit['answer'], true);

            return array_merge($hit, ['cached' => true]);
        }
        $this->enforceQuota($user);

        $plan = $this->llm->decide($question, self::TOOLS);
        if (! $plan['tool']) {
            $answer = $this->llm->compose('none', [], $plan['language']);
            $this->log($user, $question, null, $answer, false);

            return ['answer' => $answer, 'tool' => null, 'language' => $plan['language'], 'buttons' => [], 'cached' => false];
        }

        $result = $this->run($plan['tool'], $plan['args'], $user);
        if (isset($result['followup'])) {
            $this->log($user, $question, $plan['tool'], $result['followup'], false);

            return ['answer' => $result['followup'], 'tool' => $plan['tool'], 'language' => $plan['language'], 'buttons' => [], 'cached' => false];
        }
        $out = ['answer' => $this->llm->compose($plan['tool'], $result, $plan['language']), 'tool' => $plan['tool'], 'language' => $plan['language'], 'buttons' => $result['buttons'] ?? [], 'cached' => false];
        $isDraft = $plan['tool'] === 'draft_winback_campaign';
        if (! $isDraft) { // drafts are one-time actions; never serve them from cache
            Cache::put($key, $out, (int) config('messaging.ai.cache_seconds'));
        }
        $this->log($user, $question, $plan['tool'], $out['answer'], false);

        return $out;
    }

    private function enforceQuota(User $u): void
    {
        $plan = Tenant::with('plan')->find($u->tenant_id)?->plan?->code ?? 'free';
        $quota = (int) config("messaging.ai.monthly_quota.{$plan}", 20);
        $used = AiUsage::where('cached', false)->where('created_at', '>=', now()->startOfMonth())->count();
        abort_if($used >= $quota, 429, "Monthly AI question limit reached ({$quota} on the {$plan} plan). Upgrade to ask more.");
    }

    private function log(User $u, string $q, ?string $tool, string $answer, bool $cached): void
    {
        AiUsage::create(['user_id' => $u->id, 'question' => $q, 'tool' => $tool, 'tokens_in' => (int) ceil(mb_strlen($q) / 4), 'tokens_out' => (int) ceil(mb_strlen($answer) / 4), 'cost_micro_inr' => 0, 'cached' => $cached]);
    }

    // ---------------- tools ----------------
    private function run(string $tool, array $args, User $u): array
    {
        [$a, $b, $label] = $this->period($args['period'] ?? 'month');
        [$shopId, $shopLabel] = $this->shop($args['question'] ?? '');
        $base = ['period_label' => $label, 'shop_label' => $shopLabel];

        return $base + match ($tool) {
            'get_sales' => $this->reports->sales($shopId, $a, $b)['totals'],
            'get_customer_counts' => ($t = $this->reports->sales($shopId, $a, $b)['totals']) ? ['customers' => $t['customers'], 'bills' => $t['bills']] : [],
            'get_dues' => ($d = collect($this->reports->dues($shopId))) ? ['total' => (int) $d->sum('due'), 'count' => $d->count()] : [],
            'get_top_services' => ['rows' => $this->reports->sales($shopId, $a, $b)['top_services']],
            'get_staff_performance' => ['rows' => $this->reports->staff($shopId, $a, $b)],
            'get_commission' => $this->commission($args['question'] ?? '', $shopId, $a, $b),
            'draft_winback_campaign' => $this->draft($u, (int) $args['days_inactive'], (string) $args['offer_text']),
        };
    }

    private function commission(string $q, ?int $shopId, $a, $b): array
    {
        $matches = $this->matchStaff($q);
        if ($matches->isEmpty()) {
            return ['followup' => 'Which staff member? e.g. '.Staff::where('active', true)->limit(4)->pluck('name')->implode(', ').'.'];
        }
        if ($matches->count() > 1) {
            return ['followup' => 'I found more than one match: '.$matches->pluck('name')->implode(', ').'. Which one do you mean?'];
        }
        $s = $matches->first();
        $row = collect($this->reports->staff($shopId, $a, $b))->firstWhere('staff_id', $s->id) ?? ['services' => 0, 'revenue' => 0, 'commission' => 0];

        return ['staff' => $s->name, 'services' => $row['services'], 'revenue' => $row['revenue'], 'commission' => $row['commission']];
    }

    /** Fuzzy name match inside the caller's tenant only (Staff is tenant-scoped). */
    public function matchStaff(string $q)
    {
        $tokens = collect(preg_split('/[^\p{L}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY))->filter(fn ($t) => mb_strlen($t) >= 3);
        $scored = Staff::all()->map(function ($s) use ($tokens) {
            $best = 0;
            foreach (preg_split('/\s+/', mb_strtolower($s->name)) as $part) {
                foreach ($tokens as $t) {
                    similar_text($part, $t, $pct);
                    $best = max($best, $pct);
                    if (str_starts_with($t, $part) || str_starts_with($part, $t)) {
                        $best = max($best, 90);
                    }
                }
            }

            return [$s, $best];
        })->filter(fn ($x) => $x[1] >= 80)->sortByDesc(fn ($x) => $x[1]);
        if ($scored->isEmpty()) {
            return collect();
        }
        $top = $scored->first()[1];

        return $scored->filter(fn ($x) => $x[1] >= $top - 5)->map(fn ($x) => $x[0])->values();
    }

    private function shop(string $q): array
    {
        foreach (Shop::all() as $s) {
            $key = mb_strtolower(trim(preg_replace('/^.*[–-]\s*/u', '', $s->name)));
            if ($key !== '' && str_contains(mb_strtolower($q), $key) && Shop::count() > 1) {
                return [$s->id, $s->name];
            }
        }

        return [null, Shop::count() > 1 ? 'all shops' : (Shop::query()->value('name') ?? 'all shops')];
    }

    private function period(string $p): array
    {
        $now = CarbonImmutable::now(ReportService::TZ);

        return match ($p) {
            'today' => [$now->startOfDay(), $now->endOfDay(), 'today'],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 'yesterday'],
            'week' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'last 7 days'],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), 'last month'],
            default => [$now->startOfMonth(), $now->endOfDay(), 'this month'],
        };
    }

    private function draft(User $u, int $days, string $offer): array
    {
        $tpl = MessageTemplate::where('channel', 'whatsapp')->where('category', 'marketing')->where('status', 'approved')->orderBy('id')->first();
        if (! $tpl) {
            return ['followup' => 'Create an approved marketing template first (Marketing → Templates), then ask me again.'];
        }
        $filters = ['inactive_days' => $days];
        $c = Campaign::create(['name' => "Win-back {$days}d (assistant)", 'template_id' => $tpl->id, 'channel' => 'whatsapp', 'audience' => $filters, 'meta' => ['offer' => $offer, 'expiry' => now()->addDays(7)->format('d M')], 'status' => 'draft', 'created_by' => $u->id]);
        $e = $this->campaigns->estimate($filters, 'whatsapp', $u->tenant_id);

        return ['offer' => $offer, 'days' => $days, 'audience' => $e['audience'], 'credits' => $e['credits_needed'], 'balance' => $e['balance'], 'buttons' => [['id' => "send:{$c->id}", 'title' => 'Send now'], ['id' => "skip:{$c->id}", 'title' => 'Skip']]];
    }
}
