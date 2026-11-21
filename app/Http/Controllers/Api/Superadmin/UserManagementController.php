<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\ManagedUserResource;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(Role::values())],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $search = $request->input('search');

        $paginator = User::query()
            ->when($search, fn ($q) => $q->where(function ($group) use ($search) {
                $group->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (User $user) => ManagedUserResource::make($user)->resolve());

        return response()->json($paginator);
    }

    public function show(User $user): JsonResponse
    {
        $stats = null;

        if ($user->role === Role::CASHIER) {
            $handled = fn () => Order::query()->where('cashier_id', $user->getKey());

            $stats = [
                'completed' => $handled()->where('status', OrderStatus::SELESAI->value)->count(),
                'revenue' => round((float) $handled()->where('status', OrderStatus::SELESAI->value)->sum('total_amount'), 2),
                'cancelled' => Order::query()->where('cancelled_by', $user->getKey())->count(),
                'waiting' => $handled()->where('status', OrderStatus::MENUNGGU->value)->count(),
                'returns' => OrderReturn::query()->where('user_id', $user->getKey())->count(),
            ];
        }

        $activities = AuditLog::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'action', 'description', 'ip_address', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => ManagedUserResource::make($user)->resolve(),
            'stats' => $stats,
            'activities' => $activities,
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_primary'] = false;

        $user = User::query()->create($data);

        $this->audit->log(
            'user.created',
            $user,
            "Pengguna {$user->username} ({$user->role->label()}) dibuat.",
            null,
            $user->only(['name', 'username', 'email', 'role', 'is_active']),
        );

        return response()->json([
            'message' => 'Pengguna berhasil ditambahkan.',
            'data' => ManagedUserResource::make($user)->resolve(),
        ], 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $this->assertManageable($request, $user);

        $data = $request->validated();
        $newRole = Role::from($data['role']);
        $newActive = (bool) ($data['is_active'] ?? $user->is_active);
        $actor = $request->user();
        $errors = [];

        if ($user->is_primary) {
            if ($newRole !== Role::SUPERADMIN) {
                $errors['role'] = ['Role akun Superadmin utama tidak dapat diubah.'];
            }

            if (! $newActive) {
                $errors['is_active'] = ['Akun Superadmin utama tidak dapat dinonaktifkan.'];
            }
        }

        if ($actor->is($user)) {
            if ($newRole !== $user->role) {
                $errors['role'] = ['Anda tidak dapat mengubah role akun sendiri.'];
            }

            if (! $newActive) {
                $errors['is_active'] = ['Anda tidak dapat menonaktifkan akun sendiri.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($user->role === Role::SUPERADMIN && ($newRole !== Role::SUPERADMIN || ! $newActive)) {
            $this->assertAnotherSuperadmin($user);
        }

        if ($user->role === Role::CASHIER && ($newRole !== Role::CASHIER || ! $newActive)) {
            $this->assertNoWaitingOrders($user);
        }

        $user->fill($data);
        $dirty = $user->getDirty();
        $old = array_intersect_key($user->getOriginal(), $dirty);
        $user->save();

        unset($dirty['password'], $old['password']);

        if ($dirty !== []) {
            $this->audit->log('user.updated', $user, "Pengguna {$user->username} diperbarui.", $old, $dirty);
        }

        return response()->json([
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => ManagedUserResource::make($user->refresh())->resolve(),
        ]);
    }

    public function toggle(Request $request, User $user): JsonResponse
    {
        $this->assertManageable($request, $user);

        if ($user->is_active) {
            if ($request->user()->is($user)) {
                abort(409, 'Anda tidak dapat menonaktifkan akun sendiri.');
            }

            if ($user->is_primary) {
                abort(409, 'Akun Superadmin utama tidak dapat dinonaktifkan.');
            }

            if ($user->role === Role::SUPERADMIN) {
                $this->assertAnotherSuperadmin($user);
            }

            if ($user->role === Role::CASHIER) {
                $this->assertNoWaitingOrders($user);
            }
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        $this->audit->log(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            'Pengguna '.$user->username.($user->is_active ? ' diaktifkan.' : ' dinonaktifkan.'),
        );

        return response()->json([
            'message' => $user->is_active ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.',
            'data' => ManagedUserResource::make($user)->resolve(),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->assertManageable($request, $user);

        if ($request->user()->is($user)) {
            abort(409, 'Anda tidak dapat menghapus akun sendiri.');
        }

        if ($user->is_primary) {
            abort(409, 'Akun Superadmin utama tidak dapat dihapus.');
        }

        if ($user->role === Role::SUPERADMIN) {
            $this->assertAnotherSuperadmin($user);
        }

        if ($user->role === Role::CASHIER) {
            $this->assertNoWaitingOrders($user);
        }

        $snapshot = $user->only(['id', 'name', 'username', 'email', 'role']);
        $user->delete();

        $this->audit->log('user.deleted', $user, "Pengguna {$snapshot['username']} dihapus.", $snapshot);

        return response()->json(['message' => 'Pengguna berhasil dihapus.']);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => ['nullable', 'string', new StrongPassword],
        ]);

        $this->assertManageable($request, $user);

        $generated = ! $request->filled('password');
        $plain = $generated ? $this->generatePassword() : (string) $request->input('password');

        $user->forceFill(['password' => $plain])->save();

        $this->audit->log(
            'user.password_reset',
            $user,
            "Password pengguna {$user->username} direset (".($generated ? 'dibuat otomatis' : 'diisi manual').').',
        );

        return response()
            ->json([
                'message' => 'Password berhasil direset.',
                'password' => $plain,
                'generated' => $generated,
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function cashierOptions(): JsonResponse
    {
        $options = User::withTrashed()
            ->where('role', Role::CASHIER->value)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'deleted_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name.($user->trashed() ? ' (dihapus)' : ''),
            ]);

        return response()->json(['data' => $options]);
    }

    private function assertManageable(Request $request, User $target): void
    {
        if ($target->is_primary && ! $request->user()->is($target)) {
            abort(403, 'Akun Superadmin utama hanya dapat dikelola oleh pemiliknya.');
        }
    }

    private function assertAnotherSuperadmin(User $target): void
    {
        $others = User::query()
            ->role(Role::SUPERADMIN)
            ->active()
            ->whereKeyNot($target->getKey())
            ->count();

        if ($others < 1) {
            abort(409, 'Tindakan ini ditolak karena akan menghapus satu-satunya Superadmin aktif.');
        }
    }

    private function assertNoWaitingOrders(User $target): void
    {
        $waiting = Order::query()
            ->where('cashier_id', $target->getKey())
            ->where('status', OrderStatus::MENUNGGU->value)
            ->count();

        if ($waiting > 0) {
            abort(409, "Kasir ini masih menangani {$waiting} pesanan yang menunggu pembayaran. Selesaikan atau batalkan terlebih dahulu.");
        }
    }

    private function generatePassword(): string
    {
        $letters = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $chars = [];

        for ($i = 0; $i < 10; $i++) {
            $chars[] = $letters[random_int(0, strlen($letters) - 1)];
        }

        for ($i = 0; $i < 2; $i++) {
            $chars[] = $digits[random_int(0, strlen($digits) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
