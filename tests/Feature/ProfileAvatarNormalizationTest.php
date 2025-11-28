<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProfileAvatarNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_returns_avatar_absolute_for_storage_path()
    {
        $user = User::factory()->create([
            'avatar' => '/storage/avatars/100/foo.png',
        ]);

        $expected = URL::to('/storage/avatars/100/foo.png');

        $response = $this->getJson('/api/users/' . $user->id . '/public');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertSame($expected, $response->json('data.avatar'));
    }

    public function test_public_profile_returns_avatar_absolute_for_relative_avatar()
    {
        $user = User::factory()->create([
            'avatar' => 'avatars/100/foo.png',
        ]);

        $expected = URL::to('/storage/avatars/100/foo.png');

        $response = $this->getJson('/api/users/' . $user->id . '/public');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertSame($expected, $response->json('data.avatar'));
    }

    public function test_user_endpoint_returns_avatar_absolute_for_relative_avatar()
    {
        $user = User::factory()->create([
            'avatar' => 'avatars/101/bar.png',
        ]);

        $expected = URL::to('/storage/avatars/101/bar.png');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertSame($expected, $response->json('data.avatar'));
    }

    public function test_user_endpoint_keeps_absolute_avatar_url_unchanged()
    {
        $user = User::factory()->create([
            'avatar' => 'https://cdn.example.com/avatar.png',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertSame('https://cdn.example.com/avatar.png', $response->json('data.avatar'));
    }
}
