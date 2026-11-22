<?php

namespace App\Auth\Http;

use App\Models\User;
use App\Shops\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const ASSIGNABLE = ['manager', 'cashier', 'staff'];

    public function index(Request $request)
    {
        $this->ownerOnly($request);

        return User::where('tenant_id', $request->user()->tenant_id)->with('roles')->get();
    }

    public function store(Request $request): JsonResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/', 'unique:users,mobile'],
            'shop_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(self::ASSIGNABLE)],
        ]);
        $shop = Shop::findOrFail($data['shop_id']);

        $user = DB::transaction(function () use ($request, $data, $shop) {
            $user = User::create(['tenant_id' => $request->user()->tenant_id, 'name' => $data['name'], 'mobile' => $data['mobile']]);
            $this->assign($user, $shop, $data['role']);

            return $user;
        });

        return response()->json($user, 201);
    }

    public function updateRoles(Request $request, int $id): JsonResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate(['shop_id' => ['required', 'integer'], 'role' => ['required', Rule::in(self::ASSIGNABLE)]]);
        $user = User::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        abort_if($user->is_owner, 422, 'The owner role cannot be changed.');
        $this->assign($user, Shop::findOrFail($data['shop_id']), $data['role']);

        return response()->json($user->load('roles'));
    }

    private function assign(User $user, Shop $shop, string $role): void
    {
        DB::table('shop_user')->updateOrInsert(
            ['shop_id' => $shop->id, 'user_id' => $user->id],
            ['tenant_id' => $shop->tenant_id, 'created_at' => now(), 'updated_at' => now()],
        );
        setPermissionsTeamId($shop->id);
        $user->unsetRelation('roles');
        $user->syncRoles([Role::findByName($role, 'web')]);
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless($request->user()->is_owner, 403, 'Only the owner can manage users.');
    }
}
