<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function build(): EmailVerificationMail
    {
        $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

        // We send a backend callback URL so the server can verify and redirect the user safely
        $callback = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/') . '/api/auth/verify/' . urlencode($this->token);

        return $this->subject('Please verify your email')
            ->view('emails.verify_email')
            ->with(['callback' => $callback, 'frontend' => $frontend]);
    }
}
