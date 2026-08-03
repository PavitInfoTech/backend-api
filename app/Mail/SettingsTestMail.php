<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class SettingsTestMail extends Mailable
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
    }

    public function build(): SettingsTestMail
    {
        return $this->from($this->fromAddress, $this->fromName)
            ->subject('[Test] Backend API mail configuration')
            ->html('<p>This is a test email from your Backend API.</p><p>Your saved mail configuration accepted this message for delivery.</p>');
    }
}
