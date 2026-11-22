<?php

namespace App\Billing\Http;

use App\Billing\Models\Invoice;
use App\Billing\Services\CreateInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $r)
    {
        return Invoice::with('customer:id,name,mobile')->where('shop_id', $r->attributes->get('shop')->id)
            ->latest('id')->paginate(25);
    }

    public function show(Request $r, int $id)
    {
        return Invoice::with('items', 'payments', 'customer')->where('shop_id', $r->attributes->get('shop')->id)->findOrFail($id);
    }

    public function store(Request $r, CreateInvoice $create): JsonResponse
    {
        abort_unless($r->user()->is_owner || $r->user()->can('billing.create'), 403, 'You do not have permission to create bills.');
        $tenant = $r->user()->tenant_id;
        $d = $r->validate([
            'customer_id' => ['sometimes', 'nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenant)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.type' => ['required', Rule::in(['service', 'product'])],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'items.*.discount' => ['sometimes', 'integer', 'min:0'],
            'items.*.staff_id' => ['sometimes', 'nullable', Rule::exists('staff', 'id')->where('tenant_id', $tenant)],
            'discount' => ['sometimes', 'nullable', 'array'],
            'discount.type' => ['required_with:discount', Rule::in(['flat', 'percent'])],
            'discount.value' => ['required_with:discount', 'integer', 'min:0'],
            'tip' => ['sometimes', 'integer', 'min:0'],
            'payments' => ['sometimes', 'array'],
            'payments.*.method' => ['required', Rule::in(['cash', 'upi', 'card'])],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['sometimes', 'nullable', 'string', 'max:24'],
            'pay_in_full' => ['sometimes', Rule::in(['cash', 'upi', 'card'])],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'client_uuid' => ['sometimes', 'nullable', 'string', 'max:64'], // offline queue id; same meaning as idempotency_key
        ]);

        $d['idempotency_key'] = $d['idempotency_key'] ?? $d['client_uuid'] ?? null;
        $coupon = null;
        if (! empty($d['coupon_code'])) {
            $coupon = \App\Messaging\Models\Coupon::where('code', strtoupper($d['coupon_code']))->where('active', true)->first();
            abort_unless($coupon && (! $coupon->expires_at || $coupon->expires_at->isFuture()) && (! $coupon->usage_limit || $coupon->used < $coupon->usage_limit), 422, 'Invalid or expired coupon.');
            $d['discount'] = ['type' => $coupon->type, 'value' => (int) $coupon->value]; // coupon replaces any manual bill discount
        }
        $invoice = $create($r->attributes->get('shop'), $r->user()->id, $d);
        if ($coupon && $invoice->wasRecentlyCreated) {
            $coupon->increment('used');
        }

        return response()->json($invoice, $invoice->wasRecentlyCreated ? 201 : 200);
    }
}
