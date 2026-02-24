@extends('layouts.auth')

@section('title', 'Admin Login - Backend API')
@section('subtitle', 'Login to Settings Panel')

@section('content')
<form action="{{ route('admin.login') }}" method="POST" class="space-y-4">
    @csrf

    <div>
        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Admin Username</label>
        <input type="text" id="username" name="username" value="{{ old('username') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required autofocus>
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
    </div>

    <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium mt-6">Login</button>
</form>
@endsection
