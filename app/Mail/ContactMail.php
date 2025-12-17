<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public string $messageText;

    public function __construct(string $name, string $email, string $messageText)
    {
        $this->name = $name;
        $this->email = $email;
        $this->messageText = $messageText;
    }

    public function build(): ContactMail
    {
        $html = "<p>Contact message from {$this->name} ({$this->email})</p><p>" . htmlentities($this->messageText) . "</p>";

        return $this->subject('Contact form')
            ->html($html);
    }
}
