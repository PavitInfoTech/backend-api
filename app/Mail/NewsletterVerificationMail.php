<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public NewsletterSubscriber $subscriber;

    public function __construct(NewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    public function build(): NewsletterVerificationMail
    {
        $verifyUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/') . '/api/mail/newsletter/verify/' . $this->subscriber->verification_token;

        $name = $this->subscriber->name ?? 'there';

        return $this->subject('Please verify your newsletter subscription')
            ->view('emails.newsletter_verify')
            ->with([
                'name' => $name,
                'verifyUrl' => $verifyUrl,
            ]);
    }
}
