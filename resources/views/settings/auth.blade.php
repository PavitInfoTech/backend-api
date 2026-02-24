@extends('layouts.app')

@section('title', 'Auth & API Settings')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Authentication & API Settings</h1>

    <form action="{{ route('settings.update-auth') }}" method="POST" class="space-y-8">
        @csrf

        <!-- Google OAuth -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Google OAuth</h2>
            <p class="text-gray-600 text-sm mb-4">Required for Google social login</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="google_client_id" class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                    <input type="text" id="google_client_id" name="google_client_id" value="{{ old('google_client_id', \App\Models\AppSettings::getSetting('google_client_id')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label for="google_client_secret" class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                    <input type="password" id="google_client_secret" name="google_client_secret" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Leave blank to keep current">
                </div>
            </div>
        </div>

        <!-- GitHub OAuth -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">GitHub OAuth</h2>
            <p class="text-gray-600 text-sm mb-4">Required for GitHub social login</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="github_client_id" class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                    <input type="text" id="github_client_id" name="github_client_id" value="{{ old('github_client_id', \App\Models\AppSettings::getSetting('github_client_id')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label for="github_client_secret" class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                    <input type="password" id="github_client_secret" name="github_client_secret" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Leave blank to keep current">
                </div>
            </div>
        </div>

        <!-- Cloudflare Turnstile -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Cloudflare Turnstile</h2>
            <p class="text-gray-600 text-sm mb-4">CAPTCHA solution for forms (alternative to reCAPTCHA)</p>
            <div class="mb-4 flex items-center">
                <input type="checkbox" id="turnstile_enabled" name="turnstile_enabled" {{ \App\Models\AppSettings::getSetting('turnstile_enabled') ? 'checked' : '' }} class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                <label for="turnstile_enabled" class="ml-2 block text-sm text-gray-900">Enable Turnstile</label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="turnstile_site_key" class="block text-sm font-medium text-gray-700 mb-2">Site Key</label>
                    <input type="text" id="turnstile_site_key" name="turnstile_site_key" value="{{ old('turnstile_site_key', \App\Models\AppSettings::getSetting('turnstile_site_key')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label for="turnstile_secret" class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                    <input type="password" id="turnstile_secret" name="turnstile_secret" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Leave blank to keep current">
                </div>
            </div>
        </div>

        <!-- Google reCAPTCHA -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Google reCAPTCHA v2/v3</h2>
            <p class="text-gray-600 text-sm mb-4">CAPTCHA solution for forms (alternative to Turnstile)</p>
            <div class="mb-4 flex items-center">
                <input type="checkbox" id="recaptcha_enabled" name="recaptcha_enabled" {{ \App\Models\AppSettings::getSetting('recaptcha_enabled') ? 'checked' : '' }} class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                <label for="recaptcha_enabled" class="ml-2 block text-sm text-gray-900">Enable reCAPTCHA</label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="recaptcha_site_key" class="block text-sm font-medium text-gray-700 mb-2">Site Key</label>
                    <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" value="{{ old('recaptcha_site_key', \App\Models\AppSettings::getSetting('recaptcha_site_key')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label for="recaptcha_secret" class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                    <input type="password" id="recaptcha_secret" name="recaptcha_secret" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Leave blank to keep current">
                </div>
            </div>
        </div>

        <!-- Google Maps API -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Google Maps API</h2>
            <p class="text-gray-600 text-sm mb-4">For geocoding addresses and generating map pins</p>
            <div>
                <label for="google_maps_api_key" class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                <input type="password" id="google_maps_api_key" name="google_maps_api_key" value="{{ old('google_maps_api_key', \App\Models\AppSettings::getSetting('google_maps_api_key')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Leave blank to keep current">
                <p class="text-gray-500 text-xs mt-1">Requires Geocoding & Static Maps APIs enabled</p>
            </div>
        </div>

        <!-- Gorq AI Service -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Gorq AI Service</h2>
            <p class="text-gray-600 text-sm mb-4">For AI generation features</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="gorq_api_key" class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                    <input type="password" id="gorq_api_key" name="gorq_api_key" value="{{ old('gorq_api_key', \App\Models\AppSettings::getSetting('gorq_api_key')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" placeholder="Leave blank to keep current">
                </div>
                <div>
                    <label for="gorq_base_url" class="block text-sm font-medium text-gray-700 mb-2">Base URL</label>
                    <input type="url" id="gorq_base_url" name="gorq_base_url" value="{{ old('gorq_base_url', \App\Models\AppSettings::getSetting('gorq_base_url') ?? 'https://api.gorq.ai') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label for="gorq_default_model" class="block text-sm font-medium text-gray-700 mb-2">Default Model</label>
                    <input type="text" id="gorq_default_model" name="gorq_default_model" value="{{ old('gorq_default_model', \App\Models\AppSettings::getSetting('gorq_default_model') ?? 'gpt-4o-mini') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    <p class="text-gray-500 text-xs mt-1">e.g., gpt-4o-mini, gpt-4, etc.</p>
                </div>
            </div>
        </div>

        <!-- Frontend Configuration -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Frontend Configuration</h2>
            <div>
                <label for="frontend_url" class="block text-sm font-medium text-gray-700 mb-2">Frontend URL</label>
                <input type="url" id="frontend_url" name="frontend_url" value="{{ old('frontend_url', \App\Models\AppSettings::getSetting('frontend_url') ?? 'http://localhost:3000') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                <p class="text-gray-500 text-xs mt-1">URL of your frontend application (used for OAuth redirects)</p>
            </div>
        </div>

        <div class="flex justify-end border-t pt-6">
            <button type="submit" class="bg-purple-600 text-white py-2 px-6 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
