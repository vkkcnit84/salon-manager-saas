<?php

namespace App\Tenancy\Actions;

use App\Models\User;
use App\Tenancy\Models\Plan;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateTenantForOwner
{
    public const TRIAL_DAYS = 14;

    public function __invoke(User $user, string $businessName): Tenant
    {
        abort_if($user->tenant_id, 409, 'Business already created.');

        return DB::transaction(function () use ($user, $businessName) {
            $tenant = Tenant::create([
                'business_name' => $businessName,
                'owner_user_id' => $user->id,
                'plan_id' => Plan::where('code', 'pro')->value('id'), // trial runs on the top plan; downgrade handled in Sprint 5
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'status' => 'trial',
            ]);
            $user->forceFill(['tenant_id' => $tenant->id, 'is_owner' => true])->save();

            return $tenant;
        });
    }
}
