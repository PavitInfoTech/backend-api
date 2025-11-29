<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewsletterSignupMail extends Mailable
{
    use Queueable, SerializesModels;

    public NewsletterSubscriber $subscriber;

    public function __construct(NewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    public function build(): NewsletterSignupMail
    {
        $apiDomain = env('API_DOMAIN');
        if (! empty($apiDomain)) {
            $apiDomain = Str::startsWith($apiDomain, ['http://', 'https://']) ? $apiDomain : 'https://' . $apiDomain;
            $unsubscribeUrl = rtrim($apiDomain, '/') . '/mail/newsletter/unsubscribe/' . $this->subscriber->unsubscribe_token;
        } else {
            $unsubscribeUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/') . '/api/mail/newsletter/unsubscribe/' . $this->subscriber->unsubscribe_token;
        }

        $name = $this->subscriber->name ?? 'there';

        return $this->subject('Welcome to our newsletter')
            ->view('emails.newsletter_signup')
            ->with([
                'name' => $name,
                'email' => $this->subscriber->email,
                'unsubscribeUrl' => $unsubscribeUrl,
            ]);
    }
}
