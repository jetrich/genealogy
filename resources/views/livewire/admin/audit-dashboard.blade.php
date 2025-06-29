<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
            Security Audit Dashboard
        </h1>
        <p class="text-gray-600 dark:text-gray-400">
            Comprehensive audit logging and security event monitoring
        </p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
            <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['total_events']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Events</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
            <div class="text-2xl font-bold text-yellow-600">{{ number_format($stats['security_events']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Security Events</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
            <div class="text-2xl font-bold text-red-600">{{ number_format($stats['high_severity']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">High Severity</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
            <div class="text-2xl font-bold text-purple-600">{{ number_format($stats['unreviewed']) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Unreviewed</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       placeholder="Search actions, users, IPs...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                <select wire:model.live="category" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">All Categories</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Severity</label>
                <select wire:model.live="severity" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">All Severities</option>
                    @foreach($severities as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Range</label>
                <div class="flex space-x-2">
                    <input type="date" wire:model.live="dateFrom" 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <input type="date" wire:model.live="dateTo" 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
            </div>
        </div>
        
        <div class="flex items-center space-x-4 mb-4">
            <label class="flex items-center">
                <input type="checkbox" wire:model.live="onlyUnreviewed" class="mr-2">
                <span class="text-sm text-gray-700 dark:text-gray-300">Unreviewed Only</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" wire:model.live="onlySuspicious" class="mr-2">
                <span class="text-sm text-gray-700 dark:text-gray-300">Suspicious Only</span>
            </label>
        </div>
        
        <div class="flex justify-between items-center">
            <button wire:click="clearFilters" 
                    class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                Clear Filters
            </button>
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Showing {{ $auditLogs->count() }} of {{ $auditLogs->total() }} results
            </div>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer"
                            wire:click="sortBy('created_at')">
                            Timestamp
                            @if($sortBy === 'created_at')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer"
                            wire:click="sortBy('action')">
                            Action
                            @if($sortBy === 'action')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Category
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            User
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            IP Address
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer"
                            wire:click="sortBy('severity')">
                            Severity
                            @if($sortBy === 'severity')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($auditLogs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 
                                   {{ $log->suspicious_activity ? 'bg-red-50 dark:bg-red-900/20' : '' }}
                                   {{ $log->requires_review && !$log->reviewed ? 'bg-yellow-50 dark:bg-yellow-900/20' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $log->created_at->format('M j, Y H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $log->formatted_action }}
                                </div>
                                @if($log->request_id)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $log->request_id }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                           {{ $log->category === 'admin_action' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' : '' }}
                                           {{ $log->category === 'security_event' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : '' }}
                                           {{ $log->category === 'genealogy_data' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : '' }}
                                           {{ !in_array($log->category, ['admin_action', 'security_event', 'genealogy_data']) ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}">
                                    {{ ucwords(str_replace('_', ' ', $log->category)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                @if($log->user)
                                    <div>{{ $log->user->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log->user->email }}</div>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">System</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $log->ip_address }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                           {{ $log->severity === 'critical' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : '' }}
                                           {{ $log->severity === 'high' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300' : '' }}
                                           {{ $log->severity === 'medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' }}
                                           {{ $log->severity === 'low' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : '' }}">
                                    {{ ucfirst($log->severity) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex flex-col space-y-1">
                                    @if($log->requires_review)
                                        @if($log->reviewed)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                Reviewed
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                                Pending Review
                                            </span>
                                        @endif
                                    @endif
                                    @if($log->suspicious_activity)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                            Suspicious
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    @if($log->requires_review && !$log->reviewed)
                                        <button wire:click="markAsReviewed({{ $log->id }})" 
                                                class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-xs">
                                            Mark Reviewed
                                        </button>
                                    @endif
                                    @if(!$log->suspicious_activity)
                                        <button wire:click="flagAsSuspicious({{ $log->id }})" 
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs">
                                            Flag Suspicious
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                No audit logs found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $auditLogs->links() }}
        </div>
    </div>
</div>