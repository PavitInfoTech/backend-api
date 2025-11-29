<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminNewsletterNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function build(): AdminNewsletterNotificationMail
    {
        return $this->subject('New newsletter signup')
            ->view('emails.admin.newsletter_notification')
            ->with(['email' => $this->email]);
    }
}
