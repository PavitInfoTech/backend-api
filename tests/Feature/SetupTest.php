<?php

namespace Tests\Feature;

use App\Models\AdminCredential;
use App\Models\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_is_shown_when_database_installation_flag_is_false(): void
    {
        AppSettings::setSetting('app_installed', false);

        $this->get(route('setup.index'))
            ->assertOk()
            ->assertViewIs('setup.index');
    }

    public function test_database_installation_flag_is_authoritative_over_environment_flag(): void
    {
        AppSettings::setSetting('app_installed', true);
        AdminCredential::create([
            'username' => 'existing-admin',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        $this->get(route('setup.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_setup_uses_file_sessions_when_sessions_table_is_missing(): void
    {
        Schema::dropIfExists('sessions');

        config(['session.driver' => 'database']);

        $this->get(route('setup.index'))
            ->assertOk()
            ->assertViewIs('setup.index');
    }

    public function test_database_installation_succeeds_when_env_flag_cannot_be_written(): void
    {
        File::partialMock();
        File::shouldReceive('exists')
            ->once()
            ->with(base_path('.env'))
            ->andReturn(true);
        File::shouldReceive('get')
            ->once()
            ->with(base_path('.env'))
            ->andReturn("APP_INSTALLED=false\n");
        File::shouldReceive('put')
            ->once()
            ->with(base_path('.env'), \Mockery::type('string'))
            ->andReturn(false);
        Artisan::shouldReceive('call')
            ->times(4)
            ->andReturn(0);

        $this->post(route('setup.store-admin'), [
            'username' => 'database-admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('app_settings', [
            'key' => 'app_installed',
            'value' => '1',
        ]);
    }

    public function test_admin_setup_is_idempotent_and_does_not_duplicate_credentials(): void
    {
        File::partialMock();

        File::shouldReceive('exists')
            ->twice()
            ->with(base_path('.env'))
            ->andReturn(true);
        File::shouldReceive('get')
            ->twice()
            ->with(base_path('.env'))
            ->andReturn("APP_INSTALLED=false\n");
        File::shouldReceive('put')
            ->twice()
            ->with(base_path('.env'), \Mockery::type('string'))
            ->andReturn(1);
        Artisan::shouldReceive('call')
            ->times(8)
            ->andReturn(0);

        $payload = [
            'username' => 'first-admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->post(route('setup.store-admin'), $payload)
            ->assertRedirect(route('admin.login'));

        $this->post(route('setup.store-admin'), [
            ...$payload,
            'username' => 'second-admin',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('admin_credentials', 1);
        $this->assertDatabaseHas('app_settings', [
            'key' => 'app_installed',
        ]);
    }
}
