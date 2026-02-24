@extends('layouts.app')

@section('title', 'API Keys')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">API Keys Management</h1>
        <button type="button" onclick="document.getElementById('addKeyModal').classList.remove('hidden')" class="bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 text-sm font-medium">Add New Key</button>
    </div>

    @if ($apiSettings->isEmpty())
        <div class="text-center py-12">
            <p class="text-gray-500 mb-4">No API keys configured yet</p>
            <button type="button" onclick="document.getElementById('addKeyModal').classList.remove('hidden')" class="text-purple-600 hover:text-purple-800 font-medium">Add your first API key</button>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($apiSettings as $setting)
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900">{{ str_replace('api_', '', $setting->key) }}</h3>
                        <p class="text-sm text-gray-600">{{ $setting->description }}</p>
                        <p class="text-xs text-gray-500 mt-1">Created: {{ $setting->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="flex space-x-2">
                        <button type="button" onclick="document.getElementById('keyValue_{{ $setting->id }}').classList.toggle('hidden')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View
                        </button>
                        <form action="{{ route('settings.delete-api-key', $setting->id) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Are you sure?')" class="text-red-600 hover:text-red-800 text-sm font-medium">Delete</button>
                        </form>
                    </div>
                </div>
                <div id="keyValue_{{ $setting->id }}" class="hidden pl-4 pb-4 text-gray-600 text-sm">
                    <code class="bg-gray-100 p-2 rounded block break-all">{{ $setting->getValue() }}</code>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- Add Key Modal -->
<div id="addKeyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Add API Key</h2>

        <form action="{{ route('settings.store-api-key') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="key_name" class="block text-sm font-medium text-gray-700 mb-2">Key Name</label>
                <input type="text" id="key_name" name="key_name" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                <p class="text-gray-500 text-xs mt-1">e.g., Stripe, SendGrid, OpenAI</p>
            </div>

            <div>
                <label for="key_value" class="block text-sm font-medium text-gray-700 mb-2">Key Value</label>
                <textarea id="key_value" name="key_value" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required></textarea>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                <input type="text" id="description" name="description" placeholder="What is this key for?" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('addKeyModal').classList.add('hidden')" class="flex-1 py-2 px-4 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200 font-medium">Cancel</button>
                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Save Key</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Close modal when clicking outside
    document.getElementById('addKeyModal').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
</script>
@endsection
