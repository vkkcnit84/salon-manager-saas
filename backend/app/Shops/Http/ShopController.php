<?php

namespace App\Shops\Http;

use App\Shops\Actions\CreateShop;
use App\Shops\Actions\ShopSettings;
use App\Shops\Models\Shop;
use App\Tenancy\Actions\CreateTenantForOwner;
use App\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    private function shopRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'regex:/^\+?\d{8,14}$/'],
            'gstin' => ['sometimes', 'nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'invoice_prefix' => ['sometimes', 'nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9\-]+$/'],
            'tax_mode' => ['sometimes', Rule::in(['inclusive', 'exclusive'])],
            'timezone' => ['sometimes', 'timezone:all'],
        ];
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless($request->user()->is_owner, 403, 'Only the owner can do this.');
    }

    public function index(Request $request)
    {
        $query = Shop::query()->orderBy('name');
        if (! $request->user()->is_owner) {
            $query->whereIn('id', fn ($q) => $q->select('shop_id')->from('shop_user')->where('user_id', $request->user()->id));
        }

        return $query->get();
    }

    public function store(Request $request, CreateShop $create): JsonResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate($this->shopRules(true) + ShopSettings::rules());
        $tenant = Tenant::findOrFail($request->user()->tenant_id);

        return response()->json($create($tenant, $data), 201);
    }

    public function show(Request $request, int $id)
    {
        return $this->visible($request)->findOrFail($id);
    }

    public function update(Request $request, int $id)
    {
        $this->ownerOnly($request);
        $shop = Shop::findOrFail($id);
        $shop->update($request->validate($this->shopRules(false)));

        return $shop;
    }

    public function updateSettings(Request $request, int $id)
    {
        $this->ownerOnly($request);
        $shop = Shop::findOrFail($id);
        $data = $request->validate(ShopSettings::rules());
        $shop->settings = array_replace_recursive($shop->settings ?? ShopSettings::defaults(), $data['settings'] ?? []);
        $shop->save();

        return $shop;
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->ownerOnly($request);
        Shop::findOrFail($id)->delete();

        return response()->json(['message' => 'Shop deleted.']);
    }

    private function visible(Request $request)
    {
        $q = Shop::query();
        if (! $request->user()->is_owner) {
            $q->whereIn('id', fn ($s) => $s->select('shop_id')->from('shop_user')->where('user_id', $request->user()->id));
        }

        return $q;
    }

    public function business(Request $request): JsonResponse
    {
        $data = $request->validate(['business_name' => ['required', 'string', 'max:160']]);
        $tenant = (new CreateTenantForOwner)($request->user(), $data['business_name']);

        return response()->json($tenant, 201);
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $this->ownerOnly($request);
        abort_if(Shop::count() === 0, 422, 'Add your first shop before finishing onboarding.');
        Tenant::whereKey($request->user()->tenant_id)->update(['onboarding_completed_at' => now()]);

        return response()->json(['message' => 'Onboarding complete.']);
    }
}
