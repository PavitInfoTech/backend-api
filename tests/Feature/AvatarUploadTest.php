<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_upload_stores_file_and_updates_user()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.png');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/user/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $user->refresh();
        $this->assertNotEmpty($user->avatar);

        // Ensure file exists in storage
        $this->assertStringContainsString('avatars/' . $user->id, $user->avatar);
        $path = parse_url($user->avatar, PHP_URL_PATH);
        $relative = ltrim(str_replace('/storage/', '', $path), '/');
        Storage::disk('public')->assertExists($relative);
    }

    public function test_avatar_replace_deletes_previous_file()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file1 = UploadedFile::fake()->image('avatar1.png');
        $file2 = UploadedFile::fake()->image('avatar2.png');

        $this->actingAs($user, 'sanctum')->postJson('/api/user/avatar', ['avatar' => $file1]);
        $first = $user->fresh()->avatar;

        $this->actingAs($user, 'sanctum')->postJson('/api/user/avatar', ['avatar' => $file2]);
        $after = $user->fresh()->avatar;

        $this->assertNotEquals($first, $after);

        // First file should no longer exist
        $pathFirst = parse_url($first, PHP_URL_PATH);
        $rel = ltrim(str_replace('/storage/', '', $pathFirst), '/');
        Storage::disk('public')->assertMissing($rel);
    }

    public function test_public_profile_returns_limited_fields()
    {
        $user = User::factory()->create(['name' => 'Public User', 'avatar' => 'https://example.com/a.png']);

        $response = $this->getJson('/api/users/' . $user->id . '/public');

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertArrayHasKey('name', $response->json('data'));
        $this->assertArrayHasKey('avatar', $response->json('data'));
    }
}
