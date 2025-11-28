<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends ApiController
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated', 401);
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
            'name' => 'sometimes|string|max:255',
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
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'created_at' => $user->created_at,
        ];

        return $this->success($data, 'Public profile');
    }
}
