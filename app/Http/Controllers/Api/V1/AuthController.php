<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    private const MOBILE_TOKEN_TTL_MINUTES = 60;

    public function __construct(private readonly RegistrationService $registrations) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:100'],
            'otp' => ['required', 'digits:6'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:32'],
            'facility_name' => ['nullable', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $this->registrations->register($data);
        $token = $user->createToken(
            'skinsewa-mobile',
            ['*'],
            now()->addMinutes(self::MOBILE_TOKEN_TTL_MINUTES),
        );

        return response()->json([
            'message' => 'Registration completed.',
            'data' => [
                'user' => $this->userPayload($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => self::MOBILE_TOKEN_TTL_MINUTES * 60,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', mb_strtolower(trim($data['email'])))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }

        $token = $user->createToken(
            'skinsewa-mobile',
            ['*'],
            now()->addMinutes(self::MOBILE_TOKEN_TTL_MINUTES),
        );

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => $this->userPayload($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => self::MOBILE_TOKEN_TTL_MINUTES * 60,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => $this->userPayload($request->user())]]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc', 'max:100']]);

        Password::sendResetLink(['email' => mb_strtolower(trim($request->string('email')->toString()))]);

        // Keep the response identical for known and unknown addresses.
        return response()->json(['message' => 'If an account exists for that email, password reset instructions have been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:100'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Password has been reset. Please sign in again.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role(),
            'user_type' => $user->user_type,
            'is_admin' => $user->isAdministrator(),
            'roles' => $user->roles(),
            'role_label' => $user->roleLabel(),
            'phone' => $user->phone,
            'facility_name' => $user->facility_name,
            'specialty' => $user->specialty,
            'email_verified_at' => $user->email_verified_at,
        ];
    }
}
