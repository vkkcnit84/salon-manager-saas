<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_own_mobile(): void
    {
        $u = User::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'secret123']);
        Sanctum::actingAs($u);

        $this->putJson('/api/v1/me', ['mobile' => '9876543210'])->assertOk()->assertJsonPath('mobile', '9876543210');
        $this->assertSame('9876543210', $u->fresh()->mobile);
    }

    public function test_invalid_or_duplicate_mobile_is_rejected(): void
    {
        User::create(['name' => 'B', 'mobile' => '9876543210']);
        $u = User::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'secret123']);
        Sanctum::actingAs($u);

        $this->putJson('/api/v1/me', ['mobile' => '12345'])->assertStatus(422)->assertJsonValidationErrors('mobile');
        $this->putJson('/api/v1/me', ['mobile' => '9876543210'])->assertStatus(422)->assertJsonValidationErrors('mobile');
    }
}
