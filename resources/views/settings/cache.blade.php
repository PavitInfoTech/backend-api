@extends('layouts.app')

@section('title', 'Cache Management')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Cache Management</h1>

    <div class="space-y-6">
        <!-- Application Cache -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Application Cache</h2>
            <p class="text-gray-600 text-sm mb-4">Clears the general application cache (views, query results, etc.).</p>
            <form action="{{ route('settings.cache-clear') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-orange-600 text-white py-2 px-4 rounded-md hover:bg-orange-700 transition duration-200 font-medium">
                    Clear Application Cache
                </button>
            </form>
        </div>

        <!-- Configuration Cache -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Configuration Cache</h2>
            <p class="text-gray-600 text-sm mb-4">Clears the cached configuration. Use this after updating settings if changes don't take effect immediately.</p>
            <form action="{{ route('settings.cache-config-clear') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200 font-medium">
                    Clear Config Cache
                </button>
            </form>
        </div>

        <!-- Route Cache -->
        <div class="border-t pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Route Cache</h2>
            <p class="text-gray-600 text-sm mb-4">Clears the cached routes. Use this after adding or modifying routes.</p>
            <form action="{{ route('settings.cache-route-clear') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200 font-medium">
                    Clear Route Cache
                </button>
            </form>
        </div>

        <!-- Info Box -->
        <div class="border-t pt-6 bg-blue-50 p-4 rounded-md">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">ℹ️ When to Clear Cache</h3>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>• <strong>Settings Changes:</strong> Clear Config Cache after updating auth/API settings to ensure the app reads updated values.</li>
                <li>• <strong>OAuth Issues:</strong> If Google/GitHub redirect URIs or keys don't seem to be used, clear Config Cache.</li>
                <li>• <strong>CORS Issues:</strong> If allowed origins don't update, clear Config Cache.</li>
                <li>• <strong>New Routes:</strong> Clear Route Cache if you add new API routes.</li>
                <li>• <strong>General Issues:</strong> Clear Application Cache as a first troubleshooting step.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
