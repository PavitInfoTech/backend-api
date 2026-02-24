@extends('layouts.auth')

@section('title', 'Setup - Backend API')
@section('subtitle', 'Initial Setup - Configure Database')

@section('content')
<form action="{{ route('setup.store-env') }}" method="POST" class="space-y-4">
    @csrf

    <div>
        <label for="app_name" class="block text-sm font-medium text-gray-700 mb-1">Application Name</label>
        <input type="text" id="app_name" name="app_name" value="{{ old('app_name', 'Backend API') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <div>
        <label for="app_url" class="block text-sm font-medium text-gray-700 mb-1">Application URL</label>
        <input type="url" id="app_url" name="app_url" value="{{ old('app_url', 'http://localhost:8000') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <hr class="my-6">

    <h3 class="text-lg font-semibold text-gray-900 mt-6">Database Configuration</h3>

    <div>
        <label for="db_host" class="block text-sm font-medium text-gray-700 mb-1">Database Host</label>
        <input type="text" id="db_host" name="db_host" value="{{ old('db_host', 'localhost') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <div>
        <label for="db_port" class="block text-sm font-medium text-gray-700 mb-1">Database Port</label>
        <input type="number" id="db_port" name="db_port" value="{{ old('db_port', 3306) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <div>
        <label for="db_database" class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
        <input type="text" id="db_database" name="db_database" value="{{ old('db_database') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <div>
        <label for="db_username" class="block text-sm font-medium text-gray-700 mb-1">Database Username</label>
        <input type="text" id="db_username" name="db_username" value="{{ old('db_username', 'root') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <div>
        <label for="db_password" class="block text-sm font-medium text-gray-700 mb-1">Database Password</label>
        <input type="password" id="db_password" name="db_password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <hr class="my-6">

    <h3 class="text-lg font-semibold text-gray-900 mt-6">Admin Credentials</h3>

    <div>
        <label for="admin_username" class="block text-sm font-medium text-gray-700 mb-1">Admin Username</label>
        <input type="text" id="admin_username" name="admin_username" value="{{ old('admin_username') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
        <p class="text-gray-500 text-xs mt-1">Used to access the admin settings panel</p>
    </div>

    <div>
        <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-1">Admin Password</label>
        <input type="password" id="admin_password" name="admin_password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <div>
        <label for="admin_password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
        <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium mt-6">Complete Setup</button>
</form>
@endsection
