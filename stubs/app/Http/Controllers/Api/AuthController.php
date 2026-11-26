<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiForgotPasswordRequest;
use App\Http\Requests\Api\ApiLoginRequest;
use App\Http\Requests\Api\ApiRegisterRequest;
use App\Http\Requests\Api\ApiResetPasswordRequest;
use App\Mail\AccountActivationMail;
use App\Models\LoginHistory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class AuthController extends Controller
{
    /**
     * Authenticate a user and issue a Sanctum personal access token.
     */
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $password = (string) $request->validated('password');
        $throttleKey = $this->throttleKey($request, $email);
        $maxAttempts = max(1, (int) (Setting::getValue('login_max_attempts', '5') ?: 5));
        $decayMinutes = max(1, (int) (Setting::getValue('login_decay_minutes', '1') ?: 1));

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            event(new Lockout($request));

            return response()->json([
                'message' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ], 429);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, $decayMinutes * 60);

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        if ($user->isBlocked()) {
            RateLimiter::hit($throttleKey, $decayMinutes * 60);

            return response()->json([
                'message' => __('Your account has been blocked.'),
            ], 403);
        }

        if (Setting::getValue('send_activation_email_on_register', '0') === '1'
            && $user->email_verified_at === null
            && ! $user->hasRole('admin')) {
            RateLimiter::hit($throttleKey, $decayMinutes * 60);

            return response()->json([
                'message' => __('Akun Anda belum aktif. Silakan cek email Anda dan klik link aktivasi terlebih dahulu.'),
            ], 403);
        }

        RateLimiter::clear($throttleKey);
        $user->update(['last_logged_in' => now()]);

        LoginHistory::record(
            event: 'login_success',
            status: 'success',
            user: $user,
            email: $user->email,
            notes: 'API Sanctum login',
        );

        $deviceName = (string) ($request->validated('device_name') ?: ($request->userAgent() ?: 'api-token'));
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => __('Login successful.'),
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Register a new user account and issue a Sanctum personal access token.
     */
    public function register(ApiRegisterRequest $request): JsonResponse
    {
        if (Setting::getValue('is_active_register_page', '1') !== '1') {
            return response()->json([
                'message' => __('Halaman registrasi saat ini sedang dinonaktifkan.'),
            ], 403);
        }

        $sendActivation = Setting::getValue('send_activation_email_on_register', '0') === '1';

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => 'user',
            'email_verified_at' => $sendActivation ? null : now(),
            'last_logged_in' => $sendActivation ? null : now(),
        ]);

        Role::findOrCreate('user', 'web');
        $user->assignRole('user');

        if ($sendActivation) {
            $activationUrl = URL::temporarySignedRoute(
                'register.activate',
                now()->addHours(24),
                [
                    'id' => $user->id,
                    'hash' => sha1($user->email),
                ]
            );

            try {
                Mail::to($user->email)->send(new AccountActivationMail($user, $activationUrl));
            } catch (Throwable $e) {
                Log::error('Failed to send activation email via API: '.$e->getMessage());

                return response()->json([
                    'message' => __('Akun berhasil dibuat, namun gagal mengirim email aktivasi. Silakan hubungi admin.'),
                    'requires_activation' => true,
                    'user' => $this->formatUser($user),
                ], 201);
            }

            return response()->json([
                'message' => __('Registrasi berhasil! Silakan cek email Anda untuk mengaktifkan akun sebelum login.'),
                'requires_activation' => true,
                'user' => $this->formatUser($user),
            ], 201);
        }

        $deviceName = (string) ($request->validated('device_name') ?: ($request->userAgent() ?: 'api-token'));
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => __('Registration successful.'),
            'requires_activation' => false,
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => $this->formatUser($user),
        ], 201);
    }

    /**
     * Send a password reset link to the user's email address.
     */
    public function forgotPassword(ApiForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        return response()->json([
            'message' => __($status),
            'errors' => [
                'email' => [__($status)],
            ],
        ], 422);
    }

    /**
     * Reset the user's password using a valid reset token.
     */
    public function resetPassword(ApiResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        return response()->json([
            'message' => __($status),
            'errors' => [
                'email' => [__($status)],
            ],
        ], 422);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Revoke the current Sanctum access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => __('Logged out successfully.'),
        ]);
    }

    /**
     * Format user payload for API responses.
     *
     * @return array{id: int, uuid: string, name: string, email: string, role: ?string, phone: ?string, avatar_url: string, email_verified_at: ?string}
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => (string) $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
        ];
    }

    /**
     * Build the login rate-limiting key.
     */
    protected function throttleKey(Request $request, string $email): string
    {
        return 'api-login|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
    }
}
