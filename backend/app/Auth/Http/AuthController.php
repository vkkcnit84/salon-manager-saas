<?php

namespace App\Auth\Http;

use App\Auth\Services\OtpService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MOBILE = ['required', 'regex:/^[6-9]\d{9}$/'];

    public function sendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['mobile' => self::MOBILE]);
        $otp->send($data['mobile']);

        return response()->json(['message' => 'OTP sent.']);
    }

    /** Logs in an existing user, or creates an owner-to-be (no tenant yet) for a new mobile. */
    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['mobile' => self::MOBILE, 'code' => ['required', 'digits:6']]);
        if (! $otp->verify($data['mobile'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }
        $user = User::firstOrCreate(['mobile' => $data['mobile']], ['name' => $data['mobile']]);

        return $this->tokenResponse($user);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        $user = User::create($data);

        return $this->tokenResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string']]);
        $user = User::where(str_contains($data['login'], '@') ? 'email' : 'mobile', $data['login'])->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Invalid credentials.']);
        }

        return $this->tokenResponse($user);
    }

    /** Lets a signed-in user set their own name and mobile (needed for WhatsApp summaries). */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'mobile' => ['sometimes', 'nullable', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'mobile')->ignore($user->id)],
        ], ['mobile.regex' => 'Enter a valid 10-digit Indian mobile number.', 'mobile.unique' => 'This mobile number is already used by another account.']);
        $user->forceFill($data)->save();

        return response()->json($user->fresh()->load('tenant.plan'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => $user,
            'needs_onboarding' => ! $user->tenant_id,
        ], $status);
    }
}
