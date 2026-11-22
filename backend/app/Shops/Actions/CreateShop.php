<?php

namespace App\Shops\Actions;

use App\Shops\Models\Shop;
use App\Subscriptions\Services\PlanLimits;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateShop
{
    public function __construct(private PlanLimits $limits) {}

    public function __invoke(Tenant $tenant, array $data): Shop
    {
        return DB::transaction(function () use ($tenant, $data) {
            // Lock the tenant row so concurrent creates cannot exceed the plan limit.
            $locked = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $this->limits->assertCanAddShop($locked, Shop::count());

            $shop = new Shop($data);
            $shop->slug = $this->uniqueSlug($data['name']);
            $shop->settings = array_merge(ShopSettings::defaults(), $data['settings'] ?? []);
            $shop->save();

            return $shop;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        for ($i = 2; Shop::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
