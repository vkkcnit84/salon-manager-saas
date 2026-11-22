<?php

namespace App\Catalog\Http;

use App\Catalog\Models\Product;
use App\Catalog\Models\Service;
use App\Catalog\Models\ServiceCategory;
use App\Catalog\Models\ShopServicePrice;
use App\Shops\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    private function owner(Request $r): void
    {
        abort_unless($r->user()->is_owner, 403, 'Only the owner can change the catalog.');
    }

    private function serviceRules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:160'],
            'category_id' => ['sometimes', 'nullable', Rule::exists('service_categories', 'id')->where('tenant_id', request()->user()->tenant_id)],
            'price_paise' => [$req, 'integer', 'min:0'],
            'duration_min' => ['sometimes', 'integer', 'min:5', 'max:600'],
            'gst_bps' => ['sometimes', 'integer', 'min:0', 'max:2800'],
            'commission_type' => ['sometimes', 'nullable', Rule::in(['percent', 'fixed'])],
            'commission_value' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    private function hideCommission(Request $r, $model)
    {
        return $r->user()->is_owner ? $model : $model->makeHidden(['commission_type', 'commission_value']);
    }

    public function categories()
    {
        return ServiceCategory::orderBy('sort_order')->orderBy('name')->get();
    }

    public function storeCategory(Request $r): JsonResponse
    {
        $this->owner($r);
        $d = $r->validate(['name' => ['required', 'string', 'max:120'], 'sort_order' => ['sometimes', 'integer', 'min:0']]);

        return response()->json(ServiceCategory::create($d), 201);
    }

    public function services(Request $r)
    {
        $shopId = $r->attributes->get('shop')?->id;
        $overrides = $shopId ? ShopServicePrice::where('shop_id', $shopId)->pluck('price_paise', 'service_id') : collect();

        return Service::orderBy('name')->get()->map(function ($s) use ($r, $overrides) {
            $s->base_price_paise = $s->price_paise;
            $s->price_paise = $overrides[$s->id] ?? $s->price_paise;

            return $this->hideCommission($r, $s);
        });
    }

    public function storeService(Request $r): JsonResponse
    {
        $this->owner($r);

        return response()->json(Service::create($r->validate($this->serviceRules(true))), 201);
    }

    public function updateService(Request $r, int $id)
    {
        $this->owner($r);
        $s = Service::findOrFail($id);
        $s->update($r->validate($this->serviceRules(false)));

        return $s;
    }

    public function destroyService(Request $r, int $id): JsonResponse
    {
        $this->owner($r);
        Service::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function setShopPrice(Request $r, int $id): JsonResponse
    {
        $this->owner($r);
        $d = $r->validate(['shop_id' => ['required', 'integer'], 'price_paise' => ['required', 'integer', 'min:0']]);
        $service = Service::findOrFail($id);
        $shop = Shop::findOrFail($d['shop_id']);
        ShopServicePrice::updateOrCreate(['shop_id' => $shop->id, 'service_id' => $service->id], ['price_paise' => $d['price_paise']]);

        return response()->json(['message' => 'Price saved.']);
    }

    public function products()
    {
        return Product::orderBy('name')->get();
    }

    public function storeProduct(Request $r): JsonResponse
    {
        $this->owner($r);
        $d = $r->validate([
            'name' => ['required', 'string', 'max:160'], 'sku' => ['sometimes', 'nullable', 'string', 'max:60'],
            'price_paise' => ['required', 'integer', 'min:0'], 'gst_bps' => ['sometimes', 'integer', 'min:0', 'max:2800'],
            'stock' => ['sometimes', 'integer'],
        ]);

        return response()->json(Product::create($d), 201);
    }

    public function updateProduct(Request $r, int $id)
    {
        $this->owner($r);
        $p = Product::findOrFail($id);
        $p->update($r->validate([
            'name' => ['sometimes', 'string', 'max:160'], 'price_paise' => ['sometimes', 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer'], 'active' => ['sometimes', 'boolean'],
        ]));

        return $p;
    }
}
