@extends('layouts.auth')

@section('title', 'Database Setup Required - Backend API')
@section('subtitle', 'Migration not completed yet')

@section('content')
    <p class="text-gray-600 mb-4">We detected that the application is marked as installed but required database tables are missing. This may happen when the .env values were saved but migrations did not run successfully.</p>

    <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
        <p class="text-yellow-800">Required tables (users, admin_credentials, app_settings, migrations) are not found.</p>
    </div>

    <form action="{{ route('setup.run-migrations') }}" method="POST">
        @csrf
        <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Run Migrations Now</button>
    </form>

    <p class="mt-4 text-sm text-gray-500">If migration fails, check your database credentials in <code>.env</code> and retry setup.</p>

    <div class="mt-6">
        <a href="{{ route('setup.index') }}" class="text-purple-600 hover:text-purple-800">Back to setup</a>
        <span class="text-gray-500"> | </span>
        <a href="{{ route('admin.login') }}" class="text-purple-600 hover:text-purple-800">Go to login</a>
    </div>
@endsection
