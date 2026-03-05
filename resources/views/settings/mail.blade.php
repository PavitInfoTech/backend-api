@extends('layouts.app')

@section('title', 'Mail Settings')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Mail Settings</h1>

    <form action="{{ route('settings.update-mail') }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="mail_mailer" class="block text-sm font-medium text-gray-700 mb-2">Mail Driver</label>
                <select id="mail_mailer" name="mail_mailer" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    <option value="log" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'log' ? 'selected' : '' }}>Log</option>
                    <option value="smtp" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'smtp' ? 'selected' : '' }}>SMTP</option>
                    <option value="sendmail" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                    <option value="mailgun" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                    <option value="postmark" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'postmark' ? 'selected' : '' }}>Postmark</option>
                    <option value="ses" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'ses' ? 'selected' : '' }}>SES</option>
                    <option value="resend" {{ $mailSettings->where('key', 'mail_mailer')->first()?->getValue() == 'resend' ? 'selected' : '' }}>Resend</option>
                </select>
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

        <div class="flex justify-end mt-8">
            <button type="submit" class="bg-purple-600 text-white py-2 px-6 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
