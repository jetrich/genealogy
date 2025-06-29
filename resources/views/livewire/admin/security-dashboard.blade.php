<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Security Dashboard</h2>
            <p class="text-gray-600 dark:text-gray-400">Monitor security events and threat analytics</p>
        </div>
        <div class="flex space-x-2">
            <x-ts-button 
                wire:click="exportEvents" 
                color="secondary"
                icon="download"
                size="sm">
                Export Events
            </x-ts-button>
            <x-ts-button 
                wire:click="resetFilters" 
                color="outline"
                icon="refresh"
                size="sm">
                Reset Filters
            </x-ts-button>
        </div>
    </div>

    {{-- Security Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-ts-icon name="shield-exclamation" class="h-6 w-6 text-red-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Critical Events
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ $securityMetrics['critical_events'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-ts-icon name="exclamation-triangle" class="h-6 w-6 text-orange-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                High Severity
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ $securityMetrics['high_events'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-ts-icon name="eye" class="h-6 w-6 text-blue-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Unresolved Events
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ $securityMetrics['unresolved_events'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-ts-icon name="calendar" class="h-6 w-6 text-green-400" />
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Events Today
                            </dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ $securityMetrics['events_today'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Threat Intelligence Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Threats --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Top Threats (Last 30 Days)
                </h3>
                <div class="space-y-3">
                    @forelse($topThreats as $threat)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ ucwords(str_replace('_', ' ', $threat['event_type'])) }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                {{ $threat['count'] }}
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No threats detected in the last 30 days.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Suspicious IPs --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Suspicious IP Addresses (Last 7 Days)
                </h3>
                <div class="space-y-3">
                    @forelse($suspiciousIps as $ip)
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $ip['ip_address'] }}
                                </span>
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if($ip['max_severity'] === 'critical') bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                                    @elseif($ip['max_severity'] === 'high') bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100
                                    @elseif($ip['max_severity'] === 'medium') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                    @else bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100 @endif">
                                    {{ ucfirst($ip['max_severity']) }}
                                </span>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $ip['event_count'] }} events
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No suspicious IPs detected in the last 7 days.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Filters</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <x-ts-select
                        label="Severity"
                        wire:model.live="severityFilter"
                        :options="[
                            ['label' => 'All Severities', 'value' => 'all'],
                            ['label' => 'Critical', 'value' => 'critical'],
                            ['label' => 'High', 'value' => 'high'],
                            ['label' => 'Medium', 'value' => 'medium'],
                            ['label' => 'Low', 'value' => 'low']
                        ]"
                    />
                </div>
                <div>
                    <x-ts-select
                        label="Event Type"
                        wire:model.live="eventTypeFilter"
                        :options="array_merge(
                            [['label' => 'All Types', 'value' => 'all']],
                            collect($this->eventTypes)->map(fn($type) => [
                                'label' => ucwords(str_replace('_', ' ', $type)),
                                'value' => $type
                            ])->toArray()
                        )"
                    />
                </div>
                <div>
                    <x-ts-select
                        label="Time Period"
                        wire:model.live="timeFilter"
                        :options="[
                            ['label' => 'All Time', 'value' => 'all'],
                            ['label' => 'Today', 'value' => 'today'],
                            ['label' => 'This Week', 'value' => 'week'],
                            ['label' => 'This Month', 'value' => 'month']
                        ]"
                    />
                </div>
                <div>
                    <x-ts-checkbox
                        label="Resolved Only"
                        wire:model.live="showResolvedOnly"
                    />
                </div>
                <div>
                    <x-ts-checkbox
                        label="Unresolved Only"
                        wire:model.live="showUnresolvedOnly"
                    />
                </div>
            </div>
        </div>
    </div>

    {{-- Security Events Table --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Security Events</h3>
            
            @if($securityEvents->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Timestamp
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Event Type
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Severity
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    IP Address
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    User
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
                            @foreach($securityEvents as $event)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $event->created_at->format('M j, Y H:i:s') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $event->event_type_display }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($event->severity === 'critical') bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                                            @elseif($event->severity === 'high') bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100
                                            @elseif($event->severity === 'medium') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                            @else bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100 @endif">
                                            {{ ucfirst($event->severity) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $event->ip_address }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $event->user ? $event->user->email : 'Anonymous' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($event->resolved)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                Resolved
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                Open
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @if(!$event->resolved)
                                            <x-ts-button
                                                wire:click="resolveEvent({{ $event->id }})"
                                                color="success"
                                                size="xs"
                                                icon="check">
                                                Resolve
                                            </x-ts-button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-4">
                    {{ $securityEvents->links() }}
                </div>
            @else
                <div class="text-center py-8">
                    <x-ts-icon name="shield-check" class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No security events</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        No security events match your current filters.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Actions Section --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Administrative Actions</h3>
            <div class="flex flex-wrap gap-4">
                <x-ts-button 
                    wire:click="clearOldEvents(90)" 
                    color="danger"
                    icon="trash"
                    size="sm"
                    x-data
                    x-on:click="if (!confirm('Are you sure you want to clear resolved events older than 90 days?')) { $event.stopPropagation() }">
                    Clear Old Events (90+ days)
                </x-ts-button>
                <x-ts-button 
                    wire:click="exportEvents" 
                    color="secondary"
                    icon="download"
                    size="sm">
                    Export Current View
                </x-ts-button>
            </div>
        </div>
    </div>
</div>