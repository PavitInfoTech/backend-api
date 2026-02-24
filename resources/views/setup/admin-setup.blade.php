@extends('layouts.auth')

@section('title', 'Admin Setup - Backend API')
@section('subtitle', 'Create Admin Account')

@section('content')
<form action="{{ route('setup.store-admin') }}" method="POST" class="space-y-4">
    @csrf

    <p class="text-gray-600 text-sm mb-6">Create your super admin account to access the settings panel</p>

    <div>
        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Admin Username</label>
        <input type="text" id="username" name="username" value="{{ old('username') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Admin Password</label>
        <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
        <p class="text-gray-500 text-xs mt-1">Minimum 8 characters</p>
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium mt-6">Create Admin Account</button>
</form>
@endsection
