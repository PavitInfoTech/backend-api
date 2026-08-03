<?php

namespace Tests\Feature;

use App\Mail\SettingsTestMail;
use App\Models\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin' => 1];
    }

    public function test_mail_settings_page_has_independent_save_and_test_forms(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('settings.mail'))
            ->assertOk()
            ->assertSee('action="' . route('settings.update-mail') . '"', false)
            ->assertSee('action="' . route('settings.test-mail') . '"', false)
            ->assertSee('name="test_email"', false)
            ->assertSee('Send Test Email');
    }

    public function test_test_mail_sends_saved_configuration_to_requested_recipient(): void
    {
        AppSettings::setSetting('mail_mailer', 'smtp', ['category' => 'mail']);
        AppSettings::setSetting('mail_host', 'smtp.example.test', ['category' => 'mail']);
        AppSettings::setSetting('mail_port', 587, ['category' => 'mail']);
        AppSettings::setSetting('mail_from_address', 'noreply@example.test', ['category' => 'mail']);
        AppSettings::setSetting('mail_from_name', 'Backend API', ['category' => 'mail']);

        Mail::fake();

        $response = $this->withSession($this->adminSession())->post(
            route('settings.test-mail'),
            ['test_email' => 'recipient@example.test'],
        );

        $response->assertRedirect(route('settings.mail'))
            ->assertSessionHas('success');

        Mail::assertSent(SettingsTestMail::class, function (SettingsTestMail $mail): bool {
            return $mail->hasTo('recipient@example.test');
        });
    }

    public function test_test_mail_does_not_claim_success_for_log_driver(): void
    {
        AppSettings::setSetting('mail_mailer', 'log', ['category' => 'mail']);

        Mail::fake();

        $response = $this->withSession($this->adminSession())->post(
            route('settings.test-mail'),
            ['test_email' => 'recipient@example.test'],
        );

        $response->assertRedirect(route('settings.mail'))
            ->assertSessionHas('error');

        Mail::assertNothingOutgoing();
    }
}
