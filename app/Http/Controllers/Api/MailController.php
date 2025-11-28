<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends ApiController
{
    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string',
        ]);

        $to = env('MAIL_FROM_ADDRESS');

        Mail::raw("Contact message from {$data['name']} ({$data['email']})\n\n{$data['message']}", function ($m) use ($to) {
            $m->to($to)->subject('Contact form');
        });

        return $this->success(null, 'Contact message queued/sent');
    }

    public function newsletter(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        // In a real app you'd store the email in a subscribers table or 3rd-party service
        // and send a welcome/confirmation email. Here we queue a sample message.
        Mail::raw("Thanks for signing up for our newsletter.", function ($m) use ($data) {
            $m->to($data['email'])->subject('Welcome to our newsletter');
        });

        return $this->success(null, 'Newsletter signup processed');
    }

    public function passwordReset(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        // You should integrate Laravel's Password Reset flow; this is a simple placeholder
        Mail::raw("To reset your password, follow the link: <RESET_LINK>", function ($m) use ($data) {
            $m->to($data['email'])->subject('Password Reset');
        });

        return $this->success(null, 'Password reset mail queued (placeholder)');
    }
}
