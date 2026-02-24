@extends('layouts.app')

@section('title', 'Settings Dashboard')

@section('content')
<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Settings Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Mail Settings Card -->
        <a href="{{ route('settings.mail') }}" class="p-6 border border-gray-200 rounded-lg hover:shadow-lg transition duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-medium text-gray-900">Mail Settings</h2>
                    <p class="text-gray-500 text-sm">Configure email delivery</p>
                </div>
            </div>
        </a>

        <!-- Auth & API Settings Card -->
        <a href="{{ route('settings.auth') }}" class="p-6 border border-gray-200 rounded-lg hover:shadow-lg transition duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-medium text-gray-900">Auth & API</h2>
                    <p class="text-gray-500 text-sm">OAuth, captcha, AI & maps</p>
                </div>
            </div>
        </a>

        <!-- API Keys Card -->
        <a href="{{ route('settings.api') }}" class="p-6 border border-gray-200 rounded-lg hover:shadow-lg transition duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-medium text-gray-900">API Keys</h2>
                    <p class="text-gray-500 text-sm">Manage API credentials</p>
                </div>
            </div>
        </a>

        <!-- Admin Credentials Card -->
        <a href="{{ route('settings.admin-credentials') }}" class="p-6 border border-gray-200 rounded-lg hover:shadow-lg transition duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m4 5h4m-11 0h4m-4 0a4 4 0 110-5.292"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-lg font-medium text-gray-900">Admin Credentials</h2>
                    <p class="text-gray-500 text-sm">Manage admin accounts</p>
                </div>
            </div>
        </a>
    </div>

    <!-- Settings Summary -->
    <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-4">System Information</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-600">App Name:</dt>
                    <dd class="font-medium">{{ config('app.name') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Environment:</dt>
                    <dd class="font-medium">{{ config('app.env') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Laravel Version:</dt>
                    <dd class="font-medium">{{ app()->version() }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-gray-50 p-6 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Configuration Status</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-600">Mail Driver:</dt>
                    <dd class="font-medium">{{ \App\Models\AppSettings::getSetting('mail_mailer', config('mail.mailer')) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Cache Driver:</dt>
                    <dd class="font-medium">{{ config('cache.default') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Queue Driver:</dt>
                    <dd class="font-medium">{{ config('queue.default') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
