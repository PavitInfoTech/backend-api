@extends('layouts.app')

@section('title', 'Admin Credentials')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Admin Credentials Management</h1>
        <button type="button" onclick="document.getElementById('addCredentialModal').classList.remove('hidden')" class="bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 text-sm font-medium">Add Admin</button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Username</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Role</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Last Login</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Created</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($credentials as $credential)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $credential->username }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold
                                {{ $credential->role === 'super_admin' ? 'bg-red-100 text-red-800' : ($credential->role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800') }}">
                                {{ ucfirst(str_replace('_', ' ', $credential->role)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold
                                {{ $credential->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $credential->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $credential->last_login_at ? $credential->last_login_at->format('M d, Y H:i') : 'Never' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $credential->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" onclick="editCredential({{ $credential->id }}, '{{ $credential->username }}', '{{ $credential->role }}', {{ $credential->is_active ? 'true' : 'false' }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</button>
                            @if ($credentials->count() > 1)
                                <form action="{{ route('settings.delete-admin-credential', $credential->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm('Are you sure? This cannot be undone.')" class="text-red-600 hover:text-red-800 text-sm font-medium ml-2">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No admin credentials found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Credential Modal -->
<div id="addCredentialModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Add Admin Credential</h2>

        <form action="{{ route('settings.store-admin-credential') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input type="text" id="username" name="username" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                <p class="text-gray-500 text-xs mt-1">Minimum 8 characters</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                <select id="role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                    <option value="super_admin">Super Admin</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                </select>
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('addCredentialModal').classList.add('hidden')" class="flex-1 py-2 px-4 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200 font-medium">Cancel</button>
                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Credential Modal -->
<div id="editCredentialModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Edit Admin Credential</h2>

        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="edit_username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input type="text" id="edit_username" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100" disabled>
            </div>

            <div>
                <label for="edit_password" class="block text-sm font-medium text-gray-700 mb-2">New Password (Leave blank to keep current)</label>
                <input type="password" id="edit_password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div>
                <label for="edit_password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                <input type="password" id="edit_password_confirmation" name="password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div>
                <label for="edit_role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                <select id="edit_role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                    <option value="super_admin">Super Admin</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                </select>
            </div>

            <div class="flex items-center">
                <input type="checkbox" id="edit_is_active" name="is_active" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                <label for="edit_is_active" class="ml-2 block text-sm text-gray-900">Active</label>
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('editCredentialModal').classList.add('hidden')" class="flex-1 py-2 px-4 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200 font-medium">Cancel</button>
                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editCredential(id, username, role, isActive) {
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_is_active').checked = isActive;
        document.getElementById('editForm').action = `/settings/admin-credentials/${id}`;
        document.getElementById('editCredentialModal').classList.remove('hidden');
    }

    // Close modals when clicking outside
    document.getElementById('addCredentialModal')?.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });

    document.getElementById('editCredentialModal')?.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
</script>
@endsection
