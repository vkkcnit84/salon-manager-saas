<?php

namespace App\Staff\Http;

use App\Staff\Models\Staff;
use App\Subscriptions\Services\PlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    private function owner(Request $r): void
    {
        abort_unless($r->user()->is_owner, 403, 'Only the owner can manage staff.');
    }

    public function index(Request $r)
    {
        $q = Staff::with('shops:id,name')->orderBy('name');
        if ($shop = $r->attributes->get('shop')) {
            $q->whereHas('shops', fn ($s) => $s->where('shops.id', $shop->id));
        }

        return $q->get()->map(fn ($s) => $r->user()->is_owner ? $s : $s->makeHidden(['commission_type', 'commission_value']));
    }

    public function store(Request $r): JsonResponse
    {
        $this->owner($r);
        $d = $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['sometimes', 'nullable', 'regex:/^[6-9]\d{9}$/'],
            'title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'commission_type' => ['sometimes', Rule::in(['percent', 'fixed'])],
            'commission_value' => ['sometimes', 'integer', 'min:0'],
            'shop_ids' => ['sometimes', 'array'],
            'shop_ids.*' => ['integer'],
        ]);
        $staff = Staff::create(collect($d)->except('shop_ids')->all());
        $staff->shops()->syncWithPivotValues(
            \App\Shops\Models\Shop::whereIn('id', $d['shop_ids'] ?? [])->pluck('id')->all(),
            ['tenant_id' => $r->user()->tenant_id],
        );

        return response()->json($staff->load('shops:id,name'), 201);
    }

    public function update(Request $r, int $id)
    {
        $this->owner($r);
        $staff = Staff::findOrFail($id);
        $staff->update($r->validate([
            'name' => ['sometimes', 'string', 'max:120'], 'mobile' => ['sometimes', 'nullable', 'regex:/^[6-9]\d{9}$/'],
            'title' => ['sometimes', 'nullable', 'string', 'max:80'], 'active' => ['sometimes', 'boolean'],
            'commission_type' => ['sometimes', Rule::in(['percent', 'fixed'])], 'commission_value' => ['sometimes', 'integer', 'min:0'],
        ]));

        return $staff;
    }
}
