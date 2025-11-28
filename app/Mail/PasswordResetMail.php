<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;
    public string $email;

    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function build(): PasswordResetMail
    {
        $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

        $url = $frontend . '/auth/reset?token=' . urlencode($this->token) . '&email=' . urlencode($this->email);

        return $this->subject('Password Reset Request')
            ->view('emails.password_reset')
            ->with(['url' => $url]);
    }
}
