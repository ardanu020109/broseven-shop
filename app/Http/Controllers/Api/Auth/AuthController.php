<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(private AuditLogger $audit)
    {
    }

    public function csrf(): Response
    {
        return response()->noContent();
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->validated('login'));
        $password = $request->validated('password');
        $throttleKey = 'login:'.sha1(mb_strtolower($login).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->audit->log('auth.login_throttled', description: 'Login diblokir sementara karena terlalu banyak percobaan.');

            return response()->json([
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::query()
            ->where(fn ($query) => $query->where('username', $login)->orWhere('email', $login))
            ->first();

        if ($user === null) {
            Hash::make($password);
        }

        if ($user === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $this->audit->log('auth.login_failed', description: 'Login gagal.', user: $user);

            throw ValidationException::withMessages([
                'login' => ['Username/email atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            $this->audit->log('auth.login_blocked', description: 'Akun nonaktif mencoba login.', user: $user);

            return response()->json([
                'message' => 'Akun Anda dinonaktifkan. Hubungi Superadmin.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->log('auth.login', description: 'Login berhasil.', user: $user);

        return response()->json(['user' => UserResource::make($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => UserResource::make($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->log('auth.logout', description: 'Logout.');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
