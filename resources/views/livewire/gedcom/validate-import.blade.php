<div>
    {{-- GEDCOM Validation Header --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Validate GEDCOM File') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('Check your GEDCOM file for compatibility issues before importing') }}
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <x-icon name="shield-check" class="w-6 h-6 text-green-600" />
                </div>
            </div>
        </div>

        {{-- File Upload Section --}}
        <div class="p-6">
            <form wire:submit.prevent="validateGedcom">
                <div class="space-y-4">
                    {{-- File Input --}}
                    <div>
                        <x-input.file 
                            wire:model="file" 
                            label="GEDCOM File"
                            hint="Select a .ged or .gedcom file (max 50MB)"
                            accept=".ged,.gedcom"
                            :disabled="$isValidating"
                        />
                        @error('file') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex items-center justify-between">
                        <div class="flex space-x-3">
                            <x-button 
                                type="submit" 
                                color="primary"
                                :disabled="!$file || $isValidating"
                                :loading="$isValidating"
                            >
                                <x-icon name="magnifying-glass" class="w-4 h-4 mr-2" />
                                {{ __('Validate File') }}
                            </x-button>

                            @if($showResults)
                                <x-button 
                                    wire:click="resetValidation" 
                                    color="gray"
                                    :disabled="$isValidating"
                                >
                                    <x-icon name="arrow-path" class="w-4 h-4 mr-2" />
                                    {{ __('Reset') }}
                                </x-button>
                            @endif
                        </div>

                        @if($validationResults && $validationResults['can_import'])
                            <x-button 
                                wire:click="proceedToImport" 
                                color="green"
                                :disabled="$isValidating"
                            >
                                <x-icon name="arrow-right" class="w-4 h-4 mr-2" />
                                {{ __('Proceed to Import') }}
                            </x-button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Validation Results --}}
    @if($showResults && $validationResults)
        <div class="space-y-6">
            {{-- Summary Card --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Validation Summary') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- Overall Status --}}
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-3 {{ $validationResults['valid'] ? 'bg-green-100' : 'bg-red-100' }}">
                                <x-icon 
                                    :name="$validationResults['valid'] ? 'check-circle' : 'x-circle'" 
                                    class="w-8 h-8 {{ $validationResults['valid'] ? 'text-green-600' : 'text-red-600' }}"
                                />
                            </div>
                            <h4 class="text-lg font-semibold {{ $validationResults['valid'] ? 'text-green-900' : 'text-red-900' }}">
                                {{ $validationResults['valid'] ? __('Valid') : __('Has Issues') }}
                            </h4>
                            <p class="text-sm text-gray-600">
                                {{ $validationResults['can_import'] ? __('Ready for import') : __('Needs attention') }}
                            </p>
                        </div>

                        {{-- Statistics --}}
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 mb-3">
                                <x-icon name="users" class="w-8 h-8 text-blue-600" />
                            </div>
                            <h4 class="text-lg font-semibold text-blue-900">{{ number_format($validationResults['stats']['individuals']) }}</h4>
                            <p class="text-sm text-gray-600">{{ __('Individuals') }}</p>
                        </div>

                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-purple-100 mb-3">
                                <x-icon name="heart" class="w-8 h-8 text-purple-600" />
                            </div>
                            <h4 class="text-lg font-semibold text-purple-900">{{ number_format($validationResults['stats']['families']) }}</h4>
                            <p class="text-sm text-gray-600">{{ __('Families') }}</p>
                        </div>
                    </div>

                    {{-- File Information --}}
                    @if($validationResults['stats']['version'] || $validationResults['stats']['source'] || $validationResults['stats']['encoding'])
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h5 class="text-sm font-medium text-gray-900 mb-3">{{ __('File Information') }}</h5>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                @if($validationResults['stats']['version'])
                                    <div>
                                        <span class="font-medium text-gray-700">{{ __('GEDCOM Version:') }}</span>
                                        <span class="text-gray-600">{{ $validationResults['stats']['version'] }}</span>
                                    </div>
                                @endif
                                @if($validationResults['stats']['source'])
                                    <div>
                                        <span class="font-medium text-gray-700">{{ __('Source:') }}</span>
                                        <span class="text-gray-600">{{ $validationResults['stats']['source'] }}</span>
                                    </div>
                                @endif
                                @if($validationResults['stats']['encoding'])
                                    <div>
                                        <span class="font-medium text-gray-700">{{ __('Encoding:') }}</span>
                                        <span class="text-gray-600">{{ $validationResults['stats']['encoding'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Issues Section --}}
            @if(count($validationResults['issues']) > 0)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <x-icon name="exclamation-circle" class="w-5 h-5 text-red-600 mr-2" />
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ __('Critical Issues') }} ({{ count($validationResults['issues']) }})
                            </h3>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ __('These issues must be resolved before importing') }}
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            @foreach($validationResults['issues'] as $issue)
                                <div class="flex items-start space-x-3 p-4 bg-red-50 rounded-lg border border-red-200">
                                    <div class="flex-shrink-0">
                                        <x-icon 
                                            :name="getSeverityIcon($issue['severity'])" 
                                            class="w-5 h-5 text-{{ getSeverityColor($issue['severity']) }}-600"
                                        />
                                    </div>
                                    <div class="flex-grow">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-sm font-medium text-gray-900">
                                                {{ getCategoryDescription($issue['category']) }}
                                            </h5>
                                            <span class="text-xs px-2 py-1 rounded-full bg-{{ getSeverityColor($issue['severity']) }}-100 text-{{ getSeverityColor($issue['severity']) }}-800">
                                                {{ ucfirst($issue['severity']) }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-700 mt-1">{{ $issue['message'] }}</p>
                                        @if(isset($issue['location']))
                                            <p class="text-xs text-gray-500 mt-1">
                                                <x-icon name="map-pin" class="w-3 h-3 inline mr-1" />
                                                {{ __('Location:') }} {{ $issue['location'] }}
                                            </p>
                                        @endif
                                        @if(isset($issue['suggestion']))
                                            <p class="text-xs text-blue-600 mt-2">
                                                <x-icon name="light-bulb" class="w-3 h-3 inline mr-1" />
                                                {{ $issue['suggestion'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Warnings Section --}}
            @if(count($validationResults['warnings']) > 0)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <x-icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600 mr-2" />
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ __('Warnings') }} ({{ count($validationResults['warnings']) }})
                            </h3>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ __('These issues may affect import quality but won\'t prevent import') }}
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            @foreach($validationResults['warnings'] as $warning)
                                <div class="flex items-start space-x-3 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <div class="flex-shrink-0">
                                        <x-icon 
                                            :name="getSeverityIcon($warning['severity'])" 
                                            class="w-5 h-5 text-{{ getSeverityColor($warning['severity']) }}-600"
                                        />
                                    </div>
                                    <div class="flex-grow">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-sm font-medium text-gray-900">
                                                {{ getCategoryDescription($warning['category']) }}
                                            </h5>
                                            <span class="text-xs px-2 py-1 rounded-full bg-{{ getSeverityColor($warning['severity']) }}-100 text-{{ getSeverityColor($warning['severity']) }}-800">
                                                {{ ucfirst($warning['severity']) }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-700 mt-1">{{ $warning['message'] }}</p>
                                        @if(isset($warning['location']))
                                            <p class="text-xs text-gray-500 mt-1">
                                                <x-icon name="map-pin" class="w-3 h-3 inline mr-1" />
                                                {{ __('Location:') }} {{ $warning['location'] }}
                                            </p>
                                        @endif
                                        @if(isset($warning['suggestion']))
                                            <p class="text-xs text-blue-600 mt-2">
                                                <x-icon name="light-bulb" class="w-3 h-3 inline mr-1" />
                                                {{ $warning['suggestion'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Recommendations Section --}}
            @if(count($validationResults['recommendations']) > 0)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            <x-icon name="light-bulb" class="w-5 h-5 text-blue-600 mr-2" />
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Recommendations') }}</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-2">
                            @foreach($validationResults['recommendations'] as $recommendation)
                                <li class="flex items-start space-x-2">
                                    <x-icon name="check-circle" class="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                                    <span class="text-sm text-gray-700">{{ $recommendation }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>