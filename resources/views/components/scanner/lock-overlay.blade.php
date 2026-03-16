@props([
    'show' => false,
    'lockRemainingSeconds' => 0,
    'canUnlock' => false,
    'title' => __('Scanner Locked'),
    'subtitle' => __('Scanner temporarily inactive.'),
    'alertMessage' => __('Scanner locked for system security. Contact supervisor if unlock is needed.'),
    'footerMessage' => __('Please wait until time ends or contact supervisor to unlock.'),
])

@php
    // Ensure we have a valid remaining seconds value
    $remainingSeconds = $lockRemainingSeconds ?? 0;
@endphp

@if($show)
<div class="fixed inset-0 z-50 bg-black/90 backdrop-blur-sm" wire:poll.1s="checkLock">
    <div class="flex flex-col h-full">
        <div class="p-4 md:p-6 flex items-center justify-between bg-base-100/95 border-b">
            <div>
                <h2 class="text-xl md:text-2xl font-bold text-error">{{ $title }}</h2>
                <p class="text-sm opacity-70">{{ $subtitle }}</p>
            </div>
            <div class="flex gap-2">
                @if($canUnlock)
                    <x-button :label="__('Unlock')" class="btn-warning" icon="o-lock-open" wire:click="unlockScanner" />
                @endif
            </div>
        </div>
        <div class="flex-1 flex flex-col items-center justify-center text-center p-6">
            <x-icon name="o-lock-closed" :class="'w-20 h-20 text-error mb-6' . ($remainingSeconds === -1 ? ' animate-pulse' : '')" />
            
            @if($remainingSeconds === -1)
                {{-- Unlimited Lock --}}
                <div class="text-8xl font-bold text-error mb-4 animate-pulse">
                    ∞
                </div>
                <div class="text-2xl font-bold text-error mb-2">{{ __('Scanner Locked') }}</div>
                <div class="text-lg opacity-70 mb-8">{{ __('Supervisor Required') }}</div>
            @else
                {{-- Timed Lock --}}
                <div class="text-4xl font-bold text-base-content mb-2">
                    {{ sprintf('%02d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60) }}
                </div>
                <div class="text-lg opacity-70 mb-8">{{ __('Scanner will be automatically reactivated') }}</div>
            @endif
            
            <div class="max-w-md">
                <x-alert :title="__('Warning')" :description="$alertMessage" icon="o-exclamation-triangle" class="alert-error" />
            </div>
        </div>
        <div class="p-4 text-center text-sm bg-base-100/95 border-t opacity-70">
            @if($remainingSeconds === -1)
                ⛔ {{ __('Scanner locked indefinitely. Contact supervisor to unlock.') }}
            @else
                {{ $footerMessage }}
            @endif
        </div>
    </div>
</div>
@endif
