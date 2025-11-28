<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class ProfileController extends ApiController
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        // Normalize avatar to absolute URL for frontend
        if (! empty($user->avatar)) {
            $user->avatar = $this->normalizeAvatarUrl($user->avatar);
        }

        return $this->success($user, 'User profile');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        $validated = $request->validate([
            'username' => 'sometimes|string|max:255|unique:users,username,' . $user->id,
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'avatar' => 'sometimes|url',
        ]);

        $user->fill($validated);
        $user->save();

        return $this->success($user, 'Profile updated');
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $file = $validated['avatar'] ?? $request->file('avatar');
        if (! $file) {
            return $this->error('No file uploaded', 400);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $folder = 'avatars/' . $user->id;
        $filename = Str::random(12) . '_' . time() . '.' . $file->getClientOriginalExtension();

        // Remove previous avatar file if it was stored on our public disk
        if ($user->avatar) {
            $previous = $user->avatar;
            // Try to extract a relative path if avatar is stored under /storage/avatars or contains avatars/
            $relative = null;
            if (str_contains($previous, '/storage/')) {
                $idx = strpos($previous, '/storage/');
                if ($idx !== false) {
                    $relative = ltrim(substr($previous, $idx + strlen('/storage/')), '/');
                }
            } elseif (str_contains($previous, 'avatars/')) {
                // previous might contain full url, find avatars/ path
                $pos = strpos($previous, 'avatars/');
                if ($pos !== false) {
                    $relative = substr($previous, $pos);
                }
            }

            if ($relative && $disk->exists($relative)) {
                $disk->delete($relative);
            }
        }

        $path = $disk->putFileAs($folder, $file, $filename);

        $url = $disk->url($path);

        $user->avatar = $url;
        $user->save();

        return $this->success(['avatar_url' => $url], 'Avatar uploaded');
    }

    public function publicProfile(Request $request, $id)
    {
        $user = \App\Models\User::find($id);
        if (! $user) {
            return $this->error('User not found', 404);
        }

        // Return a limited public profile
        $avatar = $user->avatar;
        if (! empty($avatar)) {
            $avatar = $this->normalizeAvatarUrl($avatar);
        }

        $data = [
            'id' => $user->id,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => $avatar,
            'created_at' => $user->created_at,
        ];

        return $this->success($data, 'Public profile');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        // Delete stored avatar if present
        if ($user->avatar) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('public');
            // try to extract relative path
            $relative = null;
            if (str_contains($user->avatar, '/storage/')) {
                $idx = strpos($user->avatar, '/storage/');
                if ($idx !== false) {
                    $relative = ltrim(substr($user->avatar, $idx + strlen('/storage/')), '/');
                }
            } elseif (str_contains($user->avatar, 'avatars/')) {
                $pos = strpos($user->avatar, 'avatars/');
                if ($pos !== false) {
                    $relative = substr($user->avatar, $pos);
                }
            }

            if ($relative && $disk->exists($relative)) {
                $disk->delete($relative);
            }
        }

        // Delete all tokens
        if ($user->tokens) {
            $user->tokens()->delete();
        }

        $user->delete();

        return $this->success(null, 'Account deleted');
    }

    protected function normalizeAvatarUrl(?string $avatar): ?string
    {
        if (empty($avatar)) {
            return null;
        }

        // If it's already an absolute URL, return as is
        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }

        // If it contains '/storage/' anywhere, make it absolute
        if (Str::contains($avatar, '/storage/')) {
            $idx = strpos($avatar, '/storage/');
            $path = substr($avatar, $idx);
            return URL::to($path);
        }

        // If it contains 'avatars/' (relative), prefix with /storage/
        if (Str::contains($avatar, 'avatars/')) {
            return URL::to('/storage/' . ltrim($avatar, '/'));
        }

        // Otherwise assume it's relative and prefix with app url
        return URL::to($avatar);
    }
}
