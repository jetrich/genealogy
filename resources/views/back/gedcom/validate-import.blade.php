@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <x-tall-cards.heading 
            title="{{ __('GEDCOM Validation') }}"
            subtitle="{{ __('Validate your GEDCOM file for compatibility and quality issues before importing') }}" 
        >
            <x-slot name="actions">
                <div class="flex space-x-2">
                    <x-button 
                        href="{{ route('gedcom.importteam') }}" 
                        color="gray"
                        size="sm"
                    >
                        <x-icon name="arrow-up-tray" class="w-4 h-4 mr-2" />
                        {{ __('Direct Import') }}
                    </x-button>
                    
                    <x-button 
                        href="{{ route('help') }}" 
                        color="gray"
                        size="sm"
                    >
                        <x-icon name="question-mark-circle" class="w-4 h-4 mr-2" />
                        {{ __('Help') }}
                    </x-button>
                </div>
            </x-slot>
        </x-tall-cards.heading>

        <div class="row">
            <div class="col-12">
                @livewire('gedcom.validate-import')
            </div>
        </div>

        {{-- Info Cards --}}
        <div class="row mt-6">
            <div class="col-12 col-lg-4">
                <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                    <div class="flex items-center mb-3">
                        <x-icon name="shield-check" class="w-6 h-6 text-blue-600 mr-2" />
                        <h3 class="text-lg font-semibold text-blue-900">{{ __('Why Validate?') }}</h3>
                    </div>
                    <p class="text-sm text-blue-700">
                        {{ __('GEDCOM validation helps identify potential issues before importing, preventing failed imports and ensuring better data quality in your family tree.') }}
                    </p>
                </div>
            </div>
            
            <div class="col-12 col-lg-4">
                <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                    <div class="flex items-center mb-3">
                        <x-icon name="check-circle" class="w-6 h-6 text-green-600 mr-2" />
                        <h3 class="text-lg font-semibold text-green-900">{{ __('What We Check') }}</h3>
                    </div>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li>• {{ __('GEDCOM version compatibility') }}</li>
                        <li>• {{ __('File structure and format') }}</li>
                        <li>• {{ __('Data quality and completeness') }}</li>
                        <li>• {{ __('Cross-reference integrity') }}</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-12 col-lg-4">
                <div class="bg-yellow-50 rounded-lg p-6 border border-yellow-200">
                    <div class="flex items-center mb-3">
                        <x-icon name="exclamation-triangle" class="w-6 h-6 text-yellow-600 mr-2" />
                        <h3 class="text-lg font-semibold text-yellow-900">{{ __('Family Tree Maker') }}</h3>
                    </div>
                    <p class="text-sm text-yellow-700">
                        {{ __('Files from Family Tree Maker may need validation due to version differences. Use this tool to check compatibility before importing.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection