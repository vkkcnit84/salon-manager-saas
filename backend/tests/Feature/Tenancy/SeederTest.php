<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\Models\Plan;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_plans_seeded_and_cashier_cannot_view_commission(): void
    {
        $this->seed(RolesAndPlansSeeder::class);
        $this->seed(RolesAndPlansSeeder::class); // idempotent

        $this->assertSame(5, Role::count());
        $this->assertSame(3, Plan::count());
        $this->assertFalse(Role::findByName('cashier')->hasPermissionTo('commission.view'));
        $this->assertFalse(Role::findByName('cashier')->hasPermissionTo('billing.refund'));
        $this->assertTrue(Role::findByName('shop_owner')->hasPermissionTo('payouts.manage'));
    }
}
