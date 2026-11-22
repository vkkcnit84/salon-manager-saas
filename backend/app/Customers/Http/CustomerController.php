<?php

namespace App\Customers\Http;

use App\Customers\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $r)
    {
        $q = Customer::orderBy('name');
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('mobile', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }

        return $q->paginate(25);
    }

    public function store(Request $r): JsonResponse
    {
        abort_unless($r->user()->is_owner || $r->user()->can('customers.manage'), 403, 'You do not have permission to add customers.');
        $d = $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/', Rule::unique('customers', 'mobile')->where('tenant_id', $r->user()->tenant_id)->whereNull('deleted_at')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ] + $this->extra());
        $d = $this->withConsent($d);

        return response()->json(Customer::create($d), 201);
    }

    public function update(Request $r, int $id)
    {
        abort_unless($r->user()->is_owner || \App\Support\Permit::userCan($r->user(), 'customers.manage'), 403, 'You do not have permission to edit customers.');
        $c = Customer::findOrFail($id);
        $d = $this->withConsent($r->validate(['name' => ['sometimes', 'string', 'max:120'], 'notes' => ['sometimes', 'nullable', 'string', 'max:1000']] + $this->extra()), $c);
        $c->update($d);

        return $c;
    }

    private function extra(): array
    {
        return [
            'consent_whatsapp' => ['sometimes', 'boolean'], 'consent_sms' => ['sometimes', 'boolean'],
            'birthday' => ['sometimes', 'nullable', 'date', 'before:today'], 'anniversary' => ['sometimes', 'nullable', 'date', 'before:today'],
            'source' => ['sometimes', 'nullable', 'string', 'max:40'], 'tags' => ['sometimes', 'array'], 'tags.*' => ['string', 'max:30'],
            'opted_out' => ['sometimes', 'boolean'],
        ];
    }

    /** Records when consent was given; an explicit opt-out (or back in) is stamped here too. */
    private function withConsent(array $d, ?Customer $existing = null): array
    {
        if (($d['consent_whatsapp'] ?? false) || ($d['consent_sms'] ?? false)) {
            $d['consent_at'] = $existing?->consent_at ?? now();
        }
        if (array_key_exists('opted_out', $d)) {
            $d['opted_out_at'] = $d['opted_out'] ? ($existing?->opted_out_at ?? now()) : null;
            unset($d['opted_out']);
        }

        return $d;
    }
}
