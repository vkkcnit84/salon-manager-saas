<?php

namespace App\Expenses\Http;

use App\Expenses\Models\Expense;
use App\Shops\Models\Shop;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpenseController extends Controller
{
    public const CATEGORIES = ['Rent', 'Salaries', 'Electricity', 'Products & supplies', 'Marketing', 'Maintenance', 'Other'];

    private function gate(Request $r): void
    {
        abort_unless($r->user()->is_owner || \App\Support\Permit::userCan($r->user(), 'reports.all'), 403, 'Only the owner can manage expenses.');
    }

    public function index(Request $r)
    {
        $this->gate($r);
        $q = Expense::query()->orderByDesc('spent_on')->orderByDesc('id');
        if ($shop = $r->attributes->get('shop')) {
            $q->where('shop_id', $shop->id);
        }
        if ($r->query('from')) {
            $q->where('spent_on', '>=', $r->query('from'));
        }
        if ($r->query('to')) {
            $q->where('spent_on', '<=', $r->query('to'));
        }

        return ['categories' => self::CATEGORIES, 'data' => $q->limit(300)->get()];
    }

    public function store(Request $r): JsonResponse
    {
        $this->gate($r);
        $d = $r->validate([
            'shop_id' => ['required', 'integer'], 'category' => ['required', 'string', 'max:60'], 'amount_paise' => ['required', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:200'], 'spent_on' => ['required', 'date', 'before_or_equal:today'],
        ]);
        Shop::findOrFail($d['shop_id']);
        $e = Expense::create($d + ['created_by' => $r->user()->id]);
        Audit::record('expense.created', $e, ['amount' => $e->amount_paise]);

        return response()->json($e, 201);
    }

    public function destroy(Request $r, int $id)
    {
        $this->gate($r);
        $e = Expense::findOrFail($id);
        Audit::record('expense.deleted', $e, ['amount' => $e->amount_paise]);
        $e->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
