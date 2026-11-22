<?php

namespace App\Assistant\Services;

use App\Assistant\Contracts\LlmClient;
use App\Shared\Support\Money;

/** Offline stand-in for an LLM: keyword intent detection for English, Hinglish and Hindi. */
class RuleBasedLlmClient implements LlmClient
{
    private const INTENTS = [
        'draft_winback_campaign' => '/offer|winback|win-back|campaign|promo|ऑफर|ऑफ़र/u',
        'get_commission' => '/commission|kamai|kamaai|earning|कमीशन|कमाई|salary/u',
        'get_dues' => '/\bdue|dues|baki|baaki|udhaar|udhar|pending|बकाया|उधार/u',
        'get_top_services' => '/top service|popular|best service|sabse jyada|zyada chal|सर्विस|services?\b.*(top|best|popular)/u',
        'get_staff_performance' => '/staff|performance|best (staff|stylist)|kaun|कर्मचारी|स्टाफ/u',
        'get_customer_counts' => '/customer|customers|grahak|ग्राहक|kitne log|new customer|returning/u',
        'get_sales' => '/sale|sales|revenue|bikri|collection|business|kitna hua|kamaya|कमाया|बिक्री|income|turnover/u',
    ];

    public function decide(string $question, array $toolNames): array
    {
        $q = mb_strtolower($question);
        $lang = preg_match('/[\x{0900}-\x{097F}]/u', $question) ? 'hi' : (preg_match('/\b(kitna|kitne|hua|hai|ka|ki|ke|aaj|kal|mahine|mahina|baki|batao|dikhao|mera|meri|sabse|kaun|wala)\b/', $q) ? 'hinglish' : 'en');

        $period = 'month';
        if (preg_match('/\b(aaj|today)\b|आज/u', $q)) {
            $period = 'today';
        } elseif (preg_match('/\b(kal|yesterday)\b|कल/u', $q)) {
            $period = 'yesterday';
        } elseif (preg_match('/pichle mahine|last month|पिछले महीने/u', $q)) {
            $period = 'last_month';
        } elseif (preg_match('/hafte|hafta|week|हफ्ते|सप्ताह/u', $q)) {
            $period = 'week';
        }

        foreach (self::INTENTS as $tool => $re) {
            if (in_array($tool, $toolNames, true) && preg_match($re, $q)) {
                $args = ['period' => $period, 'question' => $question];
                if ($tool === 'draft_winback_campaign') {
                    $args += ['days_inactive' => preg_match('/(\d{2,3})\s*(din|days|day)/u', $q, $m) ? (int) $m[1] : 45, 'offer_text' => 'Flat 15% off this week'];
                }

                return ['tool' => $tool, 'args' => $args, 'language' => $lang, 'reply' => null];
            }
        }

        return ['tool' => null, 'args' => [], 'language' => $lang, 'reply' => null];
    }

    public function compose(string $tool, array $r, string $lang): string
    {
        $m = fn (int $p) => Money::paise($p)->format();
        $r += ['period_label' => '', 'shop_label' => ''];
        $scope = "{$r['period_label']} · {$r['shop_label']}";
        $hi = $lang === 'hi';
        $hg = $lang === 'hinglish';
        $t = fn (string $en, string $hinglish, string $hindi) => $hi ? $hindi : ($hg ? $hinglish : $en);

        return match ($tool) {
            'get_sales' => $t("📊 Sales ({$scope}): {$m($r['sales'])} from {$r['bills']} bills.\nAverage bill: {$m($r['avg_bill'])}.", "📊 Sales ({$scope}): {$m($r['sales'])}, {$r['bills']} bills.\nAverage bill: {$m($r['avg_bill'])}.", "📊 बिक्री ({$scope}): {$m($r['sales'])}, {$r['bills']} बिल।\nऔसत बिल: {$m($r['avg_bill'])}।"),
            'get_commission' => $t("💰 {$r['staff']}'s commission ({$scope}): {$m($r['commission'])} on {$r['services']} services (revenue {$m($r['revenue'])}).", "💰 {$r['staff']} ka commission ({$scope}): {$m($r['commission'])}, {$r['services']} services (revenue {$m($r['revenue'])}).", "💰 {$r['staff']} का कमीशन ({$scope}): {$m($r['commission'])}, {$r['services']} सर्विस।"),
            'get_staff_performance' => $t("🏆 Top staff ({$scope}):\n".$this->list($r['rows'], fn ($x) => "{$x['name']} — {$m($x['revenue'])}"), "🏆 Top staff ({$scope}):\n".$this->list($r['rows'], fn ($x) => "{$x['name']} — {$m($x['revenue'])}"), "🏆 टॉप स्टाफ ({$scope}):\n".$this->list($r['rows'], fn ($x) => "{$x['name']} — {$m($x['revenue'])}")),
            'get_dues' => $t("🧾 Pending dues ({$r['shop_label']}): {$m($r['total'])} across {$r['count']} bills.", "🧾 Baki ({$r['shop_label']}): {$m($r['total'])}, {$r['count']} bills.", "🧾 बकाया ({$r['shop_label']}): {$m($r['total'])}, {$r['count']} बिल।"),
            'get_top_services' => $t("✂️ Top services ({$scope}):\n".$this->list($r['rows'], fn ($x) => "{$x['name']} — {$m($x['revenue'])}"), "✂️ Top services ({$scope}):\n".$this->list($r['rows'], fn ($x) => "{$x['name']} — {$m($x['revenue'])}"), "✂️ टॉप सर्विस ({$scope}):\n".$this->list($r['rows'], fn ($x) => "{$x['name']} — {$m($x['revenue'])}")),
            'get_customer_counts' => $t("👥 Customers ({$scope}): {$r['customers']} unique, {$r['bills']} bills.", "👥 Customers ({$scope}): {$r['customers']} unique, {$r['bills']} bills.", "👥 ग्राहक ({$scope}): {$r['customers']} अलग, {$r['bills']} बिल।"),
            'draft_winback_campaign' => $t("📣 Draft ready: \"{$r['offer']}\" to {$r['audience']} customers inactive for {$r['days']}+ days. Cost {$r['credits']} credits (you have {$r['balance']}). Nothing is sent until you confirm.", "📣 Draft ready: \"{$r['offer']}\" — {$r['audience']} customers ({$r['days']}+ din se nahi aaye). Cost {$r['credits']} credits. Aap confirm karoge tab hi jayega.", "📣 ड्राफ्ट तैयार: {$r['audience']} ग्राहक, {$r['credits']} क्रेडिट। आपके कन्फ़र्म करने पर ही भेजा जाएगा।"),
            default => $t("I don't have that data.", "Mere paas yeh data nahi hai.", 'मेरे पास यह जानकारी नहीं है।'),
        };
    }

    private function list(array $rows, callable $f): string
    {
        return collect($rows)->take(3)->values()->map(fn ($x, $i) => ($i + 1).'. '.$f($x))->implode("\n");
    }
}
