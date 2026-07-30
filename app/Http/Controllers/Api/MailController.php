<?php

namespace App\Http\Controllers\Api;

use App\Mail\AdminNewsletterNotificationMail;
use App\Mail\NewsletterSignupMail;
use App\Mail\NewsletterVerificationMail;
use App\Mail\PasswordResetMail;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Rules\Turnstile;

class MailController extends ApiController
{
    public function contact(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string',
        ];

        if (config('services.turnstile.enabled') || config('services.recaptcha.enabled')) {
            // At least one provider token is required. Attach the rule to both
            // fields so either Turnstile or reCAPTCHA can satisfy the request.
            $rules['turnstile_token'] = ['required_without:recaptcha_token', 'nullable', 'string', new \App\Rules\CaptchaRequired('contact')];
            $rules['recaptcha_token'] = ['required_without:turnstile_token', 'nullable', 'string', new \App\Rules\CaptchaRequired('contact')];
        } else {
            $rules['turnstile_token'] = ['sometimes', 'string'];
            $rules['recaptcha_token'] = ['sometimes', 'string'];
        }

        $data = $request->validate($rules);

        // Get recipient email from AppSettings (admin configured), then fallback to config/env
        $to = \App\Models\AppSettings::getSetting('mail_to_address')
            ?: (config('mail.to_address', env('MAIL_TO_ADDRESS')) ?: config('mail.from.address', env('MAIL_FROM_ADDRESS')));

        // Send immediately without queue to ensure delivery
        try {
            \Log::info('[Contact] Sending contact form email', [
                'from' => $data['email'],
                'to' => $to,
                'driver' => config('mail.default'),
            ]);

            Mail::to($to)->sendNow(new \App\Mail\ContactMail($data['name'], $data['email'], $data['message']));
            
            \Log::info('[Contact] Contact form email sent successfully');
            return $this->success(null, 'Contact message sent successfully');
        } catch (\Exception $e) {
            \Log::error('[Contact] Contact form email failed', ['error' => $e->getMessage(), 'to' => $to, 'trace' => $e->getTraceAsString()]);
            // propagate error message back to client
            return $this->error('Failed to send contact email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Newsletter subscription with optional verification flow.
     */
    public function newsletter(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'name' => 'sometimes|string|max:255',
        ];

        if (config('services.turnstile.enabled') || config('services.recaptcha.enabled')) {
            $rules['turnstile_token'] = ['required_without:recaptcha_token', 'nullable', 'string', new \App\Rules\CaptchaRequired('newsletter')];
            $rules['recaptcha_token'] = ['required_without:turnstile_token', 'nullable', 'string', new \App\Rules\CaptchaRequired('newsletter')];
        } else {
            $rules['turnstile_token'] = ['sometimes', 'string'];
            $rules['recaptcha_token'] = ['sometimes', 'string'];
        }

        $data = $request->validate($rules);

        $email = strtolower(trim($data['email']));
        $name = $data['name'] ?? null;

        // Idempotent: don't re-add an existing subscriber
        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing) {
            return $this->success(null, 'Email already subscribed');
        }

        // Create subscriber with verification token
        $subscriber = NewsletterSubscriber::create([
            'email' => $email,
            'name' => $name,
            'verification_token' => Str::random(64),
            'unsubscribe_token' => Str::random(64),
        ]);

        // Send verification email to user immediately
        Mail::to($email)->send(new NewsletterVerificationMail($subscriber));

        // Notify admin
        $adminInbox = config('mail.from.address', env('MAIL_FROM_ADDRESS'));
        if (! empty($adminInbox)) {
            Mail::to($adminInbox)->send(new AdminNewsletterNotificationMail($email));
        }

        return $this->success(['subscriber_id' => $subscriber->id], 'Newsletter signup processed. Please check your email to verify.');
    }

    /**
     * Verify newsletter subscription via token.
     */
    public function verifyNewsletter(string $token)
    {
        $subscriber = NewsletterSubscriber::where('verification_token', $token)->first();

        if (! $subscriber) {
            return $this->error('Invalid or expired verification token', 404);
        }

        if ($subscriber->isVerified()) {
            return $this->success(null, 'Subscription already verified');
        }

        $subscriber->update([
            'verified_at' => now(),
            'verification_token' => null, // Clear token after use
        ]);

        // Send the welcome email now that they're verified
        Mail::to($subscriber->email)->send(new NewsletterSignupMail($subscriber));

        // Redirect to frontend if set and request is not JSON, otherwise return JSON
        $frontend = config('app.frontend_url', env('FRONTEND_URL'));
        if (! empty($frontend) && ! request()->expectsJson()) {
            return redirect($frontend . '/newsletter/verified');
        }

        return $this->success(null, 'Subscription verified successfully');
    }

    /**
     * Unsubscribe from newsletter via token.
     */
    public function unsubscribe(string $token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (! $subscriber) {
            return $this->error('Invalid or expired unsubscribe token', 404);
        }

        $email = $subscriber->email;
        $subscriber->delete();

        // Redirect to frontend if set and request is not JSON, otherwise return JSON
        $frontend = config('app.frontend_url', env('FRONTEND_URL'));
        if (! empty($frontend) && ! request()->expectsJson()) {
            return redirect($frontend . '/newsletter/unsubscribed');
        }

        return $this->success(['email' => $email], 'Successfully unsubscribed from newsletter');
    }

    /**
     * Send password reset email (integrated with actual flow).
     */
    public function passwordReset(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();

        // Always return success to not reveal whether email exists
        if (! $user) {
            return $this->success(null, 'If this email is registered, a password reset link has been sent');
        }

        // Create token
        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => $hashedToken, 'created_at' => now()]
        );

        // Send password reset mail using the existing PasswordResetMail mailable
        Mail::to($email)->send(new PasswordResetMail($token, $email));

        return $this->success(null, 'If this email is registered, a password reset link has been sent');
    }
}
