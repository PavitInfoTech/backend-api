<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Settings') - Backend API</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="{{ route('admin.dashboard') }}" class="text-2xl font-bold text-gray-900">Backend API</a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Admin Panel</span>
                    <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-600 hover:text-red-800">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Sidebar -->
            <div class="md:col-span-1">
                <nav class="space-y-1 bg-white rounded-lg shadow p-4">
                    <a href="{{ route('settings.index') }}" class="block px-3 py-2 rounded-md {{ request()->routeIs('settings.index') ? 'bg-purple-100 text-purple-900' : 'text-gray-600 hover:bg-gray-100' }}">Dashboard</a>
                    <a href="{{ route('settings.mail') }}" class="block px-3 py-2 rounded-md {{ request()->routeIs('settings.mail') ? 'bg-purple-100 text-purple-900' : 'text-gray-600 hover:bg-gray-100' }}">Mail Settings</a>
                    <a href="{{ route('settings.auth') }}" class="block px-3 py-2 rounded-md {{ request()->routeIs('settings.auth') ? 'bg-purple-100 text-purple-900' : 'text-gray-600 hover:bg-gray-100' }}">Auth & API</a>
                    <a href="{{ route('settings.api') }}" class="block px-3 py-2 rounded-md {{ request()->routeIs('settings.api') ? 'bg-purple-100 text-purple-900' : 'text-gray-600 hover:bg-gray-100' }}">API Keys</a>
                    <a href="{{ route('subscription-plans') }}" class="block px-3 py-2 rounded-md {{ request()->routeIs('subscription-plans') ? 'bg-purple-100 text-purple-900' : 'text-gray-600 hover:bg-gray-100' }}">Subscription Plans</a>
                    <a href="{{ route('settings.admin-credentials') }}" class="block px-3 py-2 rounded-md {{ request()->routeIs('settings.admin-credentials') ? 'bg-purple-100 text-purple-900' : 'text-gray-600 hover:bg-gray-100' }}">Admin Credentials</a>
                    <a href="{{ route('settings.cache') }}" class="block px-3 py-2 rounded-md {{ request()->routeIs('settings.cache') ? 'bg-purple-100 text-purple-900' : 'text-gray-600 hover:bg-gray-100' }}">Cache Management</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="md:col-span-3">
                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                        <h3 class="text-red-800 font-semibold mb-2">Please fix the following errors:</h3>
                        <ul class="text-red-700 text-sm space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                        <p class="text-green-800">{{ session('success') }}</p>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                        <p class="text-red-800">{{ session('error') }}</p>
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</div>

    @stack('scripts')
</body>
</html>
