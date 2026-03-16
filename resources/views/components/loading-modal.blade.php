{{-- Global Loading Modal --}}
<div 
    id="livewire-loading-modal" 
    class="modal modal-backdrop-blur"
    x-data="{ show: false }"
    x-show="show"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    style="display: none;"
    x-cloak
>
    <div class="modal-box bg-base-100/95 backdrop-blur-xl border border-base-300 shadow-2xl">
        <div class="flex flex-col items-center justify-center py-8">
            {{-- Animated Spinner --}}
            <div class="relative w-20 h-20 mb-6">
                <div class="absolute inset-0 border-4 border-base-300 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                <div class="absolute inset-2 border-4 border-secondary border-b-transparent rounded-full animate-spin-reverse"></div>
            </div>

            {{-- Loading Text --}}
            <h3 class="text-lg font-semibold text-base-content mb-2">
                {{ __('Processing') }}
            </h3>
            <p class="text-sm text-base-content/60">
                {{ __('Please wait...') }}
            </p>

            {{-- Animated Dots --}}
            <div class="flex gap-1 mt-4">
                <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay: 0s"></span>
                <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                <span class="w-2 h-2 bg-primary rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes spin-reverse {
        from {
            transform: rotate(360deg);
        }
        to {
            transform: rotate(0deg);
        }
    }
    
    .animate-spin-reverse {
        animation: spin-reverse 1.5s linear infinite;
    }

    .modal-backdrop-blur {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
    }

    [x-cloak] {
        display: none !important;
    }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        // Track the number of active Livewire requests
        let activeRequests = 0;
        
        // Listen for Livewire request start
        document.addEventListener('livewire:request', () => {
            activeRequests++;
            if (activeRequests === 1) {
                // Show modal only when first request starts
                Alpine.store('loadingModal', true);
                const modal = document.getElementById('livewire-loading-modal');
                if (modal && modal.__x) {
                    modal.__x.$data.show = true;
                }
            }
        });
        
        // Listen for Livewire request finish
        document.addEventListener('livewire:finish', () => {
            activeRequests--;
            if (activeRequests <= 0) {
                // Hide modal when all requests are complete
                activeRequests = 0;
                Alpine.store('loadingModal', false);
                const modal = document.getElementById('livewire-loading-modal');
                if (modal && modal.__x) {
                    modal.__x.$data.show = false;
                }
            }
        });

        // Handle navigation loading (wire:navigate)
        document.addEventListener('livewire:navigate', () => {
            activeRequests++;
            if (activeRequests === 1) {
                Alpine.store('loadingModal', true);
                const modal = document.getElementById('livewire-loading-modal');
                if (modal && modal.__x) {
                    modal.__x.$data.show = true;
                }
            }
        });

        // Handle navigation end
        document.addEventListener('livewire:navigated', () => {
            activeRequests--;
            if (activeRequests <= 0) {
                activeRequests = 0;
                Alpine.store('loadingModal', false);
                const modal = document.getElementById('livewire-loading-modal');
                if (modal && modal.__x) {
                    modal.__x.$data.show = false;
                }
            }
        });
    });
</script>
