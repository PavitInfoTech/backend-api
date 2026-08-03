@extends('layouts.app')

@section('title', 'Mail Settings')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Mail Settings</h1>

    <!-- Info Box -->
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
        <p class="text-blue-900 text-sm">
            <strong>Current Driver:</strong> 
            <span class="font-mono font-bold">{{ config('mail.default') }}</span>
            <br>
            <small class="text-blue-800">The application is currently using the <strong>{{ config('mail.default') }}</strong> mail driver. After changing settings below, they will take effect immediately.</small>
        </p>
    </div>

    <!-- Display current SMTP configuration for troubleshooting -->
    @if(config('mail.default') === 'smtp')
    <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-md">
        <p class="text-gray-700 text-sm">
            <strong>SMTP configuration in use:</strong><br>
            Host: <span class="font-mono">{{ config('mail.mailers.smtp.host') }}</span><br>
            Port: <span class="font-mono">{{ config('mail.mailers.smtp.port') }}</span><br>
            Username: <span class="font-mono">{{ config('mail.mailers.smtp.username') }}</span><br>
            Encryption: <span class="font-mono">{{ config('mail.mailers.smtp.encryption') ?: 'none' }}</span>
        </p>
    </div>
    @endif

    <!-- Warning if using log driver -->
    @if(config('mail.default') === 'log')
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
        <p class="text-yellow-900 text-sm">
            <strong>⚠️ Log Driver Active:</strong> Emails are being logged instead of sent. To send real emails, please select <strong>SMTP</strong> below and configure your SMTP credentials.
        </p>
    </div>
    @endif

    <form action="{{ route('settings.update-mail') }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="mail_mailer" class="block text-sm font-medium text-gray-700 mb-2">Mail Driver</label>
                <select id="mail_mailer" name="mail_mailer" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    <option value="log" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'log' ? 'selected' : '' }}>Log (Development Only)</option>
                    <option value="smtp" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'smtp' ? 'selected' : '' }}>SMTP (Production)</option>
                    <option value="sendmail" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                    <option value="mailgun" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                    <option value="postmark" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'postmark' ? 'selected' : '' }}>Postmark</option>
                    <option value="ses" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'ses' ? 'selected' : '' }}>SES</option>
                    <option value="resend" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'resend' ? 'selected' : '' }}>Resend</option>
                </select>
                <p class="text-gray-500 text-xs mt-1">Choose SMTP for sending real emails. Log is only for development/testing.</p>
            </div>

            <div>
                <label for="mail_from_address" class="block text-sm font-medium text-gray-700 mb-2">From Address</label>
                <input type="email" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address', $mailSettings->where('key', 'mail_from_address')->first()?->getValue()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
            </div>

            <div>
                <label for="mail_to_address" class="block text-sm font-medium text-gray-700 mb-2">To Address (Contact Form)</label>
                <input type="email" id="mail_to_address" name="mail_to_address" value="{{ old('mail_to_address', $mailSettings->where('key', 'mail_to_address')->first()?->getValue()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Where contact form emails will be sent">
                <p class="text-gray-500 text-xs mt-1">Recipient email for contact form submissions</p>
            </div>
        </div>

        <div>
            <label for="mail_from_name" class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
            <input type="text" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $mailSettings->where('key', 'mail_from_name')->first()?->getValue()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
        </div>

        <hr class="my-6">

        <h2 class="text-lg font-semibold text-gray-900 mb-4">SMTP Configuration</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="mail_host" class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                <input type="text" id="mail_host" name="mail_host" value="{{ old('mail_host', $mailSettings->where('key', 'mail_host')->first()?->getValue()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div>
                <label for="mail_port" class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                <input type="number" id="mail_port" name="mail_port" value="{{ old('mail_port', $mailSettings->where('key', 'mail_port')->first()?->getValue() ?? 587) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div>
                <label for="mail_username" class="block text-sm font-medium text-gray-700 mb-2">SMTP Username</label>
                <input type="text" id="mail_username" name="mail_username" value="{{ old('mail_username', $mailSettings->where('key', 'mail_username')->first()?->getValue()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div>
                <label for="mail_password" class="block text-sm font-medium text-gray-700 mb-2">SMTP Password</label>
                <input type="password" id="mail_password" name="mail_password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Leave blank to keep current">
            </div>

            <div>
                <label for="mail_encryption" class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                <select id="mail_encryption" name="mail_encryption" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    <option value="">None</option>
                    <option value="tls" {{ $mailSettings->where('key', 'mail_encryption')->first()?->getValue() == 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ $mailSettings->where('key', 'mail_encryption')->first()?->getValue() == 'ssl' ? 'selected' : '' }}>SSL</option>
                </select>
            </div>
        </div>

        <div class="border-t pt-6 mt-6">
            <div class="bg-gray-50 p-4 rounded-md">
                <p class="text-gray-700 text-sm">
                    <strong>ℹ️ How Configuration Works:</strong><br>
                    The settings you configure below are automatically applied to the application. There is no need to edit the <span class="font-mono">.env</span> file—changes here take effect immediately after you click "Save Changes".
                </p>
            </div>
        </div>

        <div class="flex justify-end items-center mt-8">
            <button type="submit" class="bg-purple-600 text-white py-2 px-6 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Save Changes</button>
        </div>
    </form>

    <div class="mt-6 border-t p-6 bg-blue-50">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Test Mail Configuration</h2>
        <p class="text-gray-600 text-sm mb-4">Save your mail settings first, then send a real test email through the selected mail transport.</p>
        <form action="{{ route('settings.test-mail') }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-4">
            @csrf
            <div class="flex-1">
                <label for="test_email" class="block text-sm font-medium text-gray-700 mb-2">Test Recipient</label>
                <input type="email" id="test_email" name="test_email" value="{{ old('test_email', $mailSettings->where('key', 'mail_to_address')->first()?->getValue()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" placeholder="you@example.com">
                <p class="text-gray-500 text-xs mt-1">If blank, the saved contact-form recipient or from address is used.</p>
            </div>
            <button type="submit" class="bg-blue-600 text-white py-2 px-6 rounded-md hover:bg-blue-700 transition duration-200 font-medium">Send Test Email</button>
        </form>
    </div>
</div>
@endsection
