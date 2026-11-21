<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => UserResource::make($request->user())->resolve()]);
    }

    public function update(ProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'username', 'email']);
        $disk = Storage::disk('public');
        $oldAvatar = $user->avatar_path;
        $newAvatar = null;

        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('avatars', 'public');
            $data['avatar_path'] = $newAvatar;
        } elseif ($request->boolean('remove_avatar')) {
            $data['avatar_path'] = null;
        }

        $user->fill($data);
        $dirty = $user->getDirty();
        $old = array_intersect_key($user->getOriginal(), $dirty);

        try {
            $user->save();
        } catch (Throwable $e) {
            if ($newAvatar !== null) {
                $disk->delete($newAvatar);
            }

            throw $e;
        }

        if ($oldAvatar !== null && $oldAvatar !== $user->avatar_path) {
            $disk->delete($oldAvatar);
        }

        unset($dirty['avatar_path'], $old['avatar_path']);

        $this->audit->log('profile.updated', $user, 'Profil diperbarui.', $old, $dirty);

        return response()->json([
            'message' => 'Profil berhasil disimpan.',
            'user' => UserResource::make($user->refresh())->resolve(),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        $request->session()->regenerate();

        $this->audit->log('profile.password_changed', $user, 'Password diganti oleh pemilik akun.');

        return response()->json(['message' => 'Password berhasil diganti.']);
    }
}
