<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Anda belum masuk.'], 401);
        }

        $allowed = array_values(array_filter(array_map(
            fn (string $role) => Role::tryFrom($role),
            $roles
        )));

        if (! $user->hasRole(...$allowed)) {
            $this->audit->log(
                'auth.access_denied',
                description: $request->method().' /'.$request->path()
            );

            return response()->json([
                'message' => 'Anda tidak memiliki hak akses untuk tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
