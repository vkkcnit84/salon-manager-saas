<?php

use App\Bot\Services\OwnerDigest;
use App\Messaging\Services\AutomationRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('automations:run', function (AutomationRunner $r) {
    $this->info('Sent: '.array_sum($r->runAll()));
})->purpose('Run birthday, anniversary, win-back and due-reminder automations');

Artisan::command('bot:nightly-summary {--force}', function (OwnerDigest $d) {
    $this->info('Sent: '.$d->sendNightly((bool) $this->option('force')));
})->purpose('Send the nightly WhatsApp summary to opted-in owners/managers');

Artisan::command('bot:alerts', function (OwnerDigest $d) {
    $this->info('Sent: '.$d->sendAlerts());
})->purpose('Send low-credit and no-bill alerts to owners');

Schedule::command('automations:run')->dailyAt('09:00')->timezone('Asia/Kolkata');
Schedule::command('bot:nightly-summary')->everyThirtyMinutes();
Schedule::command('bot:alerts')->hourly();
Schedule::command('reports:build-daily')->dailyAt('00:10')->timezone('Asia/Kolkata');

$build = function ($command) {
    $tz = \App\Reports\Services\ReportService::TZ;
    $from = $command->option('from') ? \Carbon\CarbonImmutable::parse($command->option('from'), $tz) : \Carbon\CarbonImmutable::now($tz)->subDay();
    $to = $command->option('to') ? \Carbon\CarbonImmutable::parse($command->option('to'), $tz) : $from;
    $rows = 0;
    foreach (\App\Tenancy\Models\Tenant::pluck('id') as $tid) {
        $rows += app(\App\Reports\Services\DailySummaryBuilder::class)->build($tid, $from, $to);
    }
    $command->info("Built {$rows} shop-day rows ({$from->toDateString()} → {$to->toDateString()})");
};
Artisan::command('reports:build-daily {--from=} {--to=}', fn () => $build($this))->purpose('Build daily summary tables (defaults to yesterday, shop time)');
Artisan::command('reports:rebuild {--from=} {--to=}', fn () => $build($this))->purpose('Rebuild daily summaries for a date range');

Artisan::command('campaigns:dispatch-scheduled', function () {
    $n = 0;
    foreach (\App\Tenancy\Models\Tenant::where('status', '!=', 'suspended')->pluck('id') as $tid) {
        app(\App\Tenancy\Support\TenantContext::class)->set($tid);
        foreach (\App\Messaging\Models\Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get() as $c) {
            \App\Messaging\Jobs\SendCampaignJob::dispatchSync($c->id, $tid);
            $n++;
        }
    }
    app(\App\Tenancy\Support\TenantContext::class)->clear();
    $this->info("Dispatched {$n} scheduled campaign(s).");
})->purpose('Send campaigns whose scheduled time has arrived');
Schedule::command('campaigns:dispatch-scheduled')->everyMinute();
