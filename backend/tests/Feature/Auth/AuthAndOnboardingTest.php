<?php

namespace Tests\Feature\Auth;

use App\Auth\Services\SmsGateway;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public ?string $lastSms = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        $self = $this;
        $this->app->bind(SmsGateway::class, fn () => new class($self) implements SmsGateway
        {
            public function __construct(private $t) {}

            public function send(string $mobile, string $message): void
            {
                $this->t->lastSms = $message;
            }
        });
    }

    private function otpFor(string $mobile): string
    {
        $this->postJson('/api/v1/auth/otp/send', ['mobile' => $mobile])->assertOk();
        preg_match('/(\d{6})/', $this->lastSms, $m);

        return $m[1];
    }

    private function signupOwner(string $mobile = '9876543210'): string
    {
        $res = $this->postJson('/api/v1/auth/otp/verify', ['mobile' => $mobile, 'code' => $this->otpFor($mobile)])
            ->assertOk()->assertJson(['needs_onboarding' => true]);

        return $res->json('token');
    }

    public function test_otp_signup_then_business_then_first_shop_then_complete(): void
    {
        $token = $this->signupOwner();
        $h = ['Authorization' => "Bearer $token"];

        $this->postJson('/api/v1/onboarding/business', ['business_name' => 'Glow Salon'], $h)->assertCreated();
        $this->postJson('/api/v1/onboarding/business', ['business_name' => 'Again'], $h)->assertStatus(409);

        $this->postJson('/api/v1/onboarding/complete', [], $h)->assertStatus(422); // no shop yet
        $this->postJson('/api/v1/shops', ['name' => 'Glow HYD', 'invoice_prefix' => 'HYD1-', 'gstin' => '36ABCDE1234F1Z5'], $h)
            ->assertCreated()->assertJsonPath('slug', 'glow-hyd')->assertJsonPath('settings.rounding', 'nearest_rupee');
        $this->postJson('/api/v1/onboarding/complete', [], $h)->assertOk();

        $this->getJson('/api/v1/me', $h)->assertJsonPath('tenant.business_name', 'Glow Salon')->assertJsonPath('is_owner', true);
    }

    public function test_wrong_and_reused_otp_rejected_and_attempts_limited(): void
    {
        $code = $this->otpFor('9876543210');
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '9876543210', 'code' => $wrong])->assertStatus(422);
        $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '9876543210', 'code' => $code])->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '9876543210', 'code' => $code])->assertStatus(422); // reuse

        $code = $this->otpFor('9876543211');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '9876543211', 'code' => $code === '123456' ? '654321' : '123456']);
        }
        $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '9876543211', 'code' => $code])->assertStatus(422); // locked out
    }

    public function test_invalid_mobile_rejected_and_otp_send_is_rate_limited(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['mobile' => '12345'])->assertStatus(422);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/send', ['mobile' => '9876543212'])->assertOk();
        }
        $this->postJson('/api/v1/auth/otp/send', ['mobile' => '9876543212'])->assertStatus(429);
    }

    public function test_email_password_register_login_logout(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Asha', 'email' => 'a@x.com', 'password' => 'secret-pass'])->assertCreated();
        $this->postJson('/api/v1/auth/login', ['login' => 'a@x.com', 'password' => 'bad'])->assertStatus(422);
        $token = $this->postJson('/api/v1/auth/login', ['login' => 'a@x.com', 'password' => 'secret-pass'])->assertOk()->json('token');

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1); // register token remains, login token revoked
    }
}
