<?php

namespace App\Credits\Services;

use App\Credits\Models\CreditTransaction;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/** Append-only ledger over tenants.message_credits. Debits fail closed: never below zero. */
class Credits
{
    public function balance(int $tenantId): int
    {
        return (int) Tenant::whereKey($tenantId)->value('message_credits');
    }

    public function debit(int $tenantId, int $credits, string $reference): bool
    {
        return DB::transaction(function () use ($tenantId, $credits, $reference) {
            $t = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            if ($t->message_credits < $credits) {
                return false;
            }
            $t->decrement('message_credits', $credits);
            CreditTransaction::create(['type' => 'debit', 'credits' => -$credits, 'balance_after' => $t->message_credits, 'reference' => $reference, 'tenant_id' => $tenantId]);

            return true;
        });
    }

    public function add(int $tenantId, int $credits, string $type, string $reference, ?int $amountPaise = null): int
    {
        return DB::transaction(function () use ($tenantId, $credits, $type, $reference, $amountPaise) {
            $t = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $t->increment('message_credits', $credits);
            $after = (int) $t->message_credits;
            CreditTransaction::create(['tenant_id' => $tenantId, 'type' => $type, 'credits' => $credits, 'balance_after' => $after, 'reference' => $reference, 'amount_paise' => $amountPaise]);

            return $after;
        });
    }
}
