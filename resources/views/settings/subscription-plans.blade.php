@extends('layouts.app')

@section('title', 'Subscription Plans')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Subscription Plans Management</h1>
        <div class="space-x-2">
            <button type="button" onclick="document.getElementById('bulkImportModal').classList.remove('hidden')" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200 text-sm font-medium">Bulk Import</button>
            <button type="button" onclick="document.getElementById('addPlanModal').classList.remove('hidden')" class="bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 text-sm font-medium">Add Plan</button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Price</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Interval</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Trial Days</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Created</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($plans as $plan)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div>
                                <div class="font-medium text-gray-900">{{ $plan->name }}</div>
                                <div class="text-gray-500 text-xs">{{ $plan->slug }}</div>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $plan->currency }} {{ number_format($plan->price, 2) }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ ucfirst($plan->interval) }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $plan->trial_days ?: 'None' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold
                                {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $plan->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $plan->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" onclick="editPlan({{ $plan->id }}, '{{ addslashes($plan->name) }}', '{{ $plan->slug }}', '{{ addslashes($plan->description) }}', {{ $plan->price }}, '{{ $plan->currency }}', '{{ $plan->interval }}', {{ $plan->trial_days }}, {{ $plan->is_active ? 'true' : 'false' }}, {{ json_encode($plan->features) }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</button>
                            <form action="{{ route('settings.delete-subscription-plan', $plan->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('Are you sure? This cannot be undone.')" class="text-red-600 hover:text-red-800 text-sm font-medium ml-2">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No subscription plans found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Plan Modal -->
<div id="addPlanModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Add Subscription Plan</h2>

        <form action="{{ route('settings.store-subscription-plan') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Plan Name</label>
                <input type="text" id="name" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">Slug</label>
                <input type="text" id="slug" name="slug" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                <p class="text-gray-500 text-xs mt-1">URL-friendly identifier (e.g., basic-plan)</p>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">Price</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                </div>

                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                    <input type="text" id="currency" name="currency" value="USD" maxlength="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="interval" class="block text-sm font-medium text-gray-700 mb-2">Billing Interval</label>
                    <select id="interval" name="interval" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                        <option value="month">Monthly</option>
                        <option value="year">Yearly</option>
                        <option value="once">One-time</option>
                    </select>
                </div>

                <div>
                    <label for="trial_days" class="block text-sm font-medium text-gray-700 mb-2">Trial Days</label>
                    <input type="number" id="trial_days" name="trial_days" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
            </div>

            <div>
                <label for="features" class="block text-sm font-medium text-gray-700 mb-2">Features (one per line)</label>
                <textarea id="features" name="features" rows="4" placeholder="Feature 1&#10;Feature 2&#10;Feature 3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"></textarea>
            </div>

            <div class="flex items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-900">Active</label>
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('addPlanModal').classList.add('hidden')" class="flex-1 py-2 px-4 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200 font-medium">Cancel</button>
                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Create Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Plan Modal -->
<div id="editPlanModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Edit Subscription Plan</h2>

        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-2">Plan Name</label>
                <input type="text" id="edit_name" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
            </div>

            <div>
                <label for="edit_slug" class="block text-sm font-medium text-gray-700 mb-2">Slug</label>
                <input type="text" id="edit_slug" name="slug" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                <p class="text-gray-500 text-xs mt-1">URL-friendly identifier (e.g., basic-plan)</p>
            </div>

            <div>
                <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea id="edit_description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="edit_price" class="block text-sm font-medium text-gray-700 mb-2">Price</label>
                    <input type="number" id="edit_price" name="price" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                </div>

                <div>
                    <label for="edit_currency" class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                    <input type="text" id="edit_currency" name="currency" maxlength="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="edit_interval" class="block text-sm font-medium text-gray-700 mb-2">Billing Interval</label>
                    <select id="edit_interval" name="interval" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500" required>
                        <option value="month">Monthly</option>
                        <option value="year">Yearly</option>
                        <option value="once">One-time</option>
                    </select>
                </div>

                <div>
                    <label for="edit_trial_days" class="block text-sm font-medium text-gray-700 mb-2">Trial Days</label>
                    <input type="number" id="edit_trial_days" name="trial_days" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                </div>
            </div>

            <div>
                <label for="edit_features" class="block text-sm font-medium text-gray-700 mb-2">Features (one per line)</label>
                <textarea id="edit_features" name="features" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"></textarea>
            </div>

            <div class="flex items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                <label for="edit_is_active" class="ml-2 block text-sm text-gray-900">Active</label>
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('editPlanModal').classList.add('hidden')" class="flex-1 py-2 px-4 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200 font-medium">Cancel</button>
                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 font-medium">Update Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editPlan(id, name, slug, description, price, currency, interval, trialDays, isActive, features) {
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_slug').value = slug;
        document.getElementById('edit_description').value = description || '';
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_currency').value = currency;
        
        // Map database interval values to form values
        const intervalMap = {
            'monthly': 'month',
            'yearly': 'year',
            'one-time': 'once',
            'month': 'month',
            'year': 'year'
        };
        document.getElementById('edit_interval').value = intervalMap[interval] || interval;
        
        document.getElementById('edit_trial_days').value = trialDays || '';
        document.getElementById('edit_is_active').checked = isActive;

        // Convert features array to newline-separated string
        let featuresText = '';
        if (features && Array.isArray(features)) {
            featuresText = features.join('\n');
        }
        document.getElementById('edit_features').value = featuresText;

        const baseUrl = "{{ route('settings.update-subscription-plan', 'PLAN_ID') }}".replace('PLAN_ID', id);
        document.getElementById('editForm').action = baseUrl;
        document.getElementById('editPlanModal').classList.remove('hidden');
    }

    // Auto-generate slug from name
    document.getElementById('name')?.addEventListener('input', function() {
        const slugField = document.getElementById('slug');
        if (!slugField.value) {
            slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        }
    });

    document.getElementById('edit_name')?.addEventListener('input', function() {
        const slugField = document.getElementById('edit_slug');
        if (!slugField.value) {
            slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        }
    });

    // Close modals when clicking outside
    document.getElementById('addPlanModal')?.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });

    document.getElementById('editPlanModal')?.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
</script>

<!-- Bulk Import Modal -->
<div id="bulkImportModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Bulk Import Plans</h2>

        <p class="text-gray-600 text-sm mb-4">Paste a JSON array of subscription plans. Required fields: <code class="bg-gray-100 px-2 py-1 rounded">slug</code>, <code class="bg-gray-100 px-2 py-1 rounded">name</code>, <code class="bg-gray-100 px-2 py-1 rounded">price</code></p>

        <form action="{{ route('settings.bulk-import-subscription-plans') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="json_data" class="block text-sm font-medium text-gray-700 mb-2">JSON Data</label>
                <textarea id="json_data" name="json_data" rows="12" placeholder='[&#10;  {&#10;    "slug": "basic",&#10;    "name": "Basic Plan",&#10;    "price": 0,&#10;    "description": "Free plan",&#10;    "features": ["Feature 1", "Feature 2"],&#10;    "currency": "USD",&#10;    "interval": "month"&#10;  }&#10;]' class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 font-mono text-xs"></textarea>
                <p class="text-gray-500 text-xs mt-1">Optional fields: description, features (array), currency (default USD), interval (default month), trial_days, is_active, popular</p>
                <p class="text-gray-500 text-xs mt-1"><strong>Interval values:</strong> "month" → Monthly, "year" → Yearly, "once" → One-time</p>
            </div>

            <div class="bg-blue-50 border border-blue-200 p-3 rounded-md text-sm text-blue-800">
                <strong>Example:</strong>
                <pre class="text-xs mt-2 overflow-auto">{
  "slug": "pro",
  "name": "Studio",
  "price": 29.99,
  "description": "Professional plan",
  "features": ["Unlimited", "Priority Support", "Studio Quality"],
  "currency": "USD",
  "interval": "month",
  "is_active": true
}</pre>
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('bulkImportModal').classList.add('hidden')" class="flex-1 py-2 px-4 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200 font-medium">Cancel</button>
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200 font-medium">Import Plans</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Close bulk import modal when clicking outside
    document.getElementById('bulkImportModal')?.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
</script>
@endsection