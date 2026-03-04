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

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Callback URLs</label>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <input type="text" readonly value="{{ env('API_DOMAIN') ? 'https://'.env('API_DOMAIN').'/auth/google/callback' : url('/api/auth/google/callback') }}" class="w-full px-3 py-2 border border-gray-200 rounded-l-md bg-gray-50" id="google_callback_primary">
                        <button type="button" data-target="google_callback_primary" class="copy-btn bg-white border border-l-0 px-3 py-2 rounded-r-md">Copy</button>
                    </div>
                    <p class="text-gray-500 text-xs">Use this URL as the OAuth redirect URI in Google Console.</p>
                </div>
            </div>
            <div class="mt-4">
                <label for="google_redirect" class="block text-sm font-medium text-gray-700 mb-2">Override Redirect URI (optional)</label>
                <input type="url" id="google_redirect" name="google_redirect" value="{{ old('google_redirect', \App\Models\AppSettings::getSetting('google_redirect', env('GOOGLE_REDIRECT') ?? (\App\Models\AppSettings::getSetting('frontend_url') ? rtrim(\App\Models\AppSettings::getSetting('frontend_url'), '/') . '/api/auth/google/callback' : url('/api/auth/google/callback')))) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                <p class="text-gray-500 text-xs mt-1">Optional: explicitly set the redirect URI used by the backend when exchanging tokens.</p>
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

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Callback URLs</label>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <input type="text" readonly value="{{ env('API_DOMAIN') ? 'https://'.env('API_DOMAIN').'/auth/github/callback' : url('/api/auth/github/callback') }}" class="w-full px-3 py-2 border border-gray-200 rounded-l-md bg-gray-50" id="github_callback_primary">
                        <button type="button" data-target="github_callback_primary" class="copy-btn bg-white border border-l-0 px-3 py-2 rounded-r-md">Copy</button>
                    </div>
                    <p class="text-gray-500 text-xs">Use this URL as the OAuth redirect URI in GitHub App settings.</p>
                </div>
            </div>
            <div class="mt-4">
                <label for="github_redirect" class="block text-sm font-medium text-gray-700 mb-2">Override Redirect URI (optional)</label>
                <input type="url" id="github_redirect" name="github_redirect" value="{{ old('github_redirect', \App\Models\AppSettings::getSetting('github_redirect', env('GITHUB_REDIRECT') ?? (\App\Models\AppSettings::getSetting('frontend_url') ? rtrim(\App\Models\AppSettings::getSetting('frontend_url'), '/') . '/api/auth/github/callback' : url('/api/auth/github/callback')))) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                <p class="text-gray-500 text-xs mt-1">Optional: explicitly set the redirect URI used by the backend when exchanging tokens.</p>
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

        <!-- Allowed Origins -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Allowed Origins (CORS)</h2>
            <p class="text-gray-600 text-sm mb-4">List origins allowed to access this API. Enter one per line or comma separated.</p>
            <div>
                <label for="allowed_origins" class="block text-sm font-medium text-gray-700 mb-2">Allowed Origins</label>
                <textarea id="allowed_origins" name="allowed_origins" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">{{ old('allowed_origins', is_array(\App\Models\AppSettings::getSetting('allowed_origins')) ? implode("\n", \App\Models\AppSettings::getSetting('allowed_origins')) : \App\Models\AppSettings::getSetting('allowed_origins')) }}</textarea>
                <p class="text-gray-500 text-xs mt-1">Example: https://app.example.com or http://localhost:3000</p>
            </div>
        </div>

        <div class="border-t pt-6">
            <div class="flex items-start space-x-3">
                <div class="flex items-center h-5">
                    <input id="write_env" name="write_env" type="checkbox" {{ old('write_env') ? 'checked' : '' }} class="h-4 w-4 text-purple-600 border-gray-300 rounded">
                </div>
                <div class="ml-3 text-sm">
                    <label for="write_env" class="font-medium text-gray-700">Also write selected keys to .env</label>
                    <p class="text-gray-500">When checked, selected OAuth/API keys will be persisted to the <span class="font-mono">.env</span> file. Note: some services require restarting the app or clearing config cache for changes to take effect.</p>
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-purple-600 text-white py-2 px-6 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Save Changes</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.copy-btn').forEach(function(btn){
        btn.addEventListener('click', async function(){
            var target = document.getElementById(btn.getAttribute('data-target'));
            if(!target) return;
            var text = target.value || target.innerText || '';

            // Prefer modern clipboard API with permission request
            try {
                if (navigator.permissions) {
                    try {
                        var res = await navigator.permissions.query({ name: 'clipboard-write' });
                        // if state is 'denied' will throw below
                    } catch (permErr) {
                        // ignore permission query failures
                    }
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // Fallback to execCommand
                    target.focus();
                    target.select();
                    document.execCommand('copy');
                }

                var old = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function(){ btn.textContent = old; }, 1500);
            } catch (err) {
                // Show a fallback prompt so user can manually copy
                try {
                    prompt('Copy the callback URL', text);
                } catch (e) {
                    // last resort: no-op
                }
            }
        });
    });
});
</script>
@endpush
