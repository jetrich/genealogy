<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Permission Management</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Manage user permissions with full audit trail and justification requirements.
        </p>
    </div>

    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Selection Panel -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Select User</h3>
            </div>
            
            <div class="p-6">
                <!-- Search Input -->
                <div class="mb-4">
                    <input 
                        type="text" 
                        wire:model.live="search" 
                        placeholder="Search users by name or email..."
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                    >
                </div>

                <!-- User List -->
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach ($users as $user)
                        <div 
                            wire:click="selectUser({{ $user->id }})"
                            class="p-3 border rounded cursor-pointer transition-colors
                                {{ $selectedUser && $selectedUser->id === $user->id 
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' 
                                    : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                        >
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $user->name }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $user->email }}
                                    </p>
                                </div>
                                @if ($user->is_developer)
                                    <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">
                                        Legacy Developer
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>

        <!-- Permission Management Panel -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    @if ($selectedUser)
                        Permissions for {{ $selectedUser->name }}
                    @else
                        Select a user to manage permissions
                    @endif
                </h3>
            </div>
            
            @if ($selectedUser)
                <div class="p-6">
                    <!-- Grant Permission Button -->
                    <div class="mb-6">
                        <button 
                            wire:click="showGrantPermissionModal"
                            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                            Grant Permission
                        </button>
                    </div>

                    <!-- Current Permissions -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Current Permissions</h4>
                        
                        @if ($userPermissions->count() > 0 || $selectedUser->is_developer)
                            <div class="space-y-2">
                                @if ($selectedUser->is_developer)
                                    <div class="flex justify-between items-center p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded">
                                        <div>
                                            <p class="font-medium text-red-800 dark:text-red-200">Legacy Developer Access</p>
                                            <p class="text-sm text-red-600 dark:text-red-300">Full system access via is_developer flag</p>
                                        </div>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">LEGACY</span>
                                    </div>
                                @endif

                                @foreach ($userPermissions as $permission)
                                    <div class="flex justify-between items-center p-3 border border-gray-200 dark:border-gray-600 rounded">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $permission->name }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $permission->description }}</p>
                                            @if ($permission->is_sensitive)
                                                <span class="inline-block mt-1 px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded">
                                                    SENSITIVE
                                                </span>
                                            @endif
                                        </div>
                                        <button 
                                            wire:click="showRevokePermissionModal('{{ $permission->name }}')"
                                            class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                                        >
                                            Revoke
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 dark:text-gray-400">No permissions granted</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Grant Permission Modal -->
    @if ($showGrantModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="closeModals">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800" wire:click.stop>
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Grant Permission to {{ $selectedUser->name }}
                    </h3>
                    
                    <form wire:submit.prevent="grantPermission">
                        <!-- Permission Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Select Permission
                            </label>
                            <select 
                                wire:model="selectedPermission" 
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                            >
                                <option value="">Choose a permission...</option>
                                @foreach ($permissionsByCategory as $category => $permissions)
                                    <optgroup label="{{ ucwords(str_replace('_', ' ', $category)) }}">
                                        @foreach ($permissions as $permission)
                                            @if ($availablePermissions->contains('name', $permission->name))
                                                <option value="{{ $permission->name }}">
                                                    {{ $permission->name }} 
                                                    {{ $permission->is_sensitive ? '(SENSITIVE)' : '' }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('selectedPermission')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Justification -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Justification <span class="text-red-500">*</span>
                            </label>
                            <textarea 
                                wire:model="justification" 
                                rows="3" 
                                placeholder="Explain why this permission is needed..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                            ></textarea>
                            @error('justification')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @error('permission')
                            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                                {{ $message }}
                            </div>
                        @enderror

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3">
                            <button 
                                type="button" 
                                wire:click="closeModals"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-400 dark:hover:bg-gray-500"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                Grant Permission
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Revoke Permission Modal -->
    @if ($showRevokeModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="closeModals">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800" wire:click.stop>
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Revoke Permission from {{ $selectedUser->name }}
                    </h3>
                    
                    <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded">
                        <p class="text-red-800 dark:text-red-200">
                            <strong>Warning:</strong> You are about to revoke the permission "{{ $permissionToRevoke }}" from this user.
                        </p>
                    </div>
                    
                    <form wire:submit.prevent="revokePermission">
                        <!-- Justification -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Reason for Revocation <span class="text-red-500">*</span>
                            </label>
                            <textarea 
                                wire:model="justification" 
                                rows="3" 
                                placeholder="Explain why this permission is being revoked..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                            ></textarea>
                            @error('justification')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @error('permission')
                            <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                                {{ $message }}
                            </div>
                        @enderror

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3">
                            <button 
                                type="button" 
                                wire:click="closeModals"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-400 dark:hover:bg-gray-500"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                Revoke Permission
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>