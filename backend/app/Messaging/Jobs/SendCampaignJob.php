<?php

namespace App\Messaging\Jobs;

use App\Messaging\Models\Campaign;
use App\Messaging\Services\CampaignService;
use App\Tenancy\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** IDs only in the payload. Production: add Redis throttling per provider and Bus::batch chunks. */
class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $campaignId, public int $tenantId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(CampaignService $svc): void
    {
        app(TenantContext::class)->set($this->tenantId);
        $svc->send(Campaign::findOrFail($this->campaignId));
    }
}
