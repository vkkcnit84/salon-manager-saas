<?php

namespace App\Shared\Http\Middleware;

use App\Shops\Models\Shop;
use App\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets tenant from the authenticated user and validates X-Shop-Id against that user's shops.
 * Unknown, other-tenant and no-access shop IDs all return the same 404 so nothing leaks.
 */
class ResolveShopContext
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $user = $request->user();
        abort_if(! $user || ! $user->tenant_id, 403, 'No tenant access.');

        abort_if(\App\Tenancy\Models\Tenant::whereKey($user->tenant_id)->value('status') === 'suspended', 403, 'This account is suspended. Please contact support.');

        $this->context->set((int) $user->tenant_id);

        if ($mode === 'tenant') {
            return $next($request);
        }

        $header = $request->header('X-Shop-Id');
        if ($header === null || $header === '') {
            // Owners may omit the header only on routes that allow the "All shops" view.
            abort_if($mode !== 'optional' || ! $user->is_owner, 422, 'X-Shop-Id header is required.');

            return $next($request);
        }

        $query = Shop::query()->whereKey((int) $header);
        if (! $user->is_owner) {
            $query->whereIn('id', fn ($q) => $q->select('shop_id')->from('shop_user')->where('user_id', $user->id));
        }
        $shop = $query->first();
        abort_if(! $shop, 404, 'Shop not found.');

        $this->context->set((int) $user->tenant_id, $shop->id);
        $request->attributes->set('shop', $shop);
        setPermissionsTeamId($shop->id);

        return $next($request);
    }
}
