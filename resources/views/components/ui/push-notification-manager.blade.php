@props(['show' => true])

<div x-data="pushNotification" x-init="init()" {{ $attributes->merge(['class' => '']) }} x-show="{{ $show }}">
    {{-- Notification Permission Banner --}}
    <div x-show="!isSupported" class="alert alert-warning mb-4">
        <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
        <span>Push notifications are not supported in your browser.</span>
    </div>

    <div x-show="isSupported && !isSubscribed && permissionState !== 'denied'" class="alert alert-info mb-4">
        <x-icon name="o-bell" class="w-6 h-6" />
        <div class="flex-1">
            <h3 class="font-bold">Enable Scanner Lock Notifications</h3>
            <div class="text-sm">Get notified when users are locked out of scanners you supervise.</div>
        </div>
        <button @click="subscribe()" :disabled="isLoading" class="btn btn-sm btn-primary">
            <span x-show="!isLoading">Enable</span>
            <span x-show="isLoading" class="loading loading-spinner loading-sm"></span>
        </button>
    </div>

    <div x-show="permissionState === 'denied'" class="alert alert-error mb-4">
        <x-icon name="o-x-circle" class="w-6 h-6" />
        <div class="flex-1">
            <h3 class="font-bold">Notifications Blocked</h3>
            <div class="text-sm">Please enable notifications in your browser settings.</div>
        </div>
    </div>

    <div x-show="isSubscribed" class="alert alert-success mb-4">
        <x-icon name="o-check-circle" class="w-6 h-6" />
        <div class="flex-1">
            <h3 class="font-bold">Notifications Enabled</h3>
            <div class="text-sm">You'll receive alerts for scanner locks.</div>
        </div>
        <button @click="unsubscribe()" :disabled="isLoading" class="btn btn-sm btn-ghost">
            <span x-show="!isLoading">Disable</span>
            <span x-show="isLoading" class="loading loading-spinner loading-sm"></span>
        </button>
    </div>
</div>

<script>
(function registerPushNotificationComponent() {
    const register = () => {
        Alpine.data('pushNotification', () => ({
        isSupported: false,
        isSubscribed: false,
        isLoading: false,
        permissionState: 'default',
        vapidPublicKey: '{{ config('webpush.vapid.public_key') }}',

        init() {
            this.checkSupport();
            this.checkSubscription();
        },

        checkSupport() {
            this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        },

        async checkSubscription() {
            if (!this.isSupported) return;

            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                this.isSubscribed = subscription !== null;
                this.permissionState = Notification.permission;
            } catch (error) {
                console.error('Error checking subscription:', error);
            }
        },

        async subscribe() {
            if (!this.isSupported) return;

            this.isLoading = true;

            try {
                // Request notification permission
                const permission = await Notification.requestPermission();
                this.permissionState = permission;

                if (permission !== 'granted') {
                    alert('Notification permission denied. Please enable notifications in your browser settings.');
                    this.isLoading = false;
                    return;
                }

                // Register service worker if not already registered
                let registration = await navigator.serviceWorker.getRegistration();
                if (!registration) {
                    registration = await navigator.serviceWorker.register('/sw.js');
                    await navigator.serviceWorker.ready;
                }

                // Convert VAPID key
                const convertedVapidKey = this.urlBase64ToUint8Array(this.vapidPublicKey);

                // Subscribe to push notifications
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedVapidKey
                });

                // Send subscription to server
                const response = await fetch('{{ route('push.subscribe') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(subscription)
                });

                if (response.ok) {
                    this.isSubscribed = true;
                    console.log('Push notification subscription successful');
                } else {
                    throw new Error('Failed to save subscription on server');
                }
            } catch (error) {
                console.error('Error subscribing to push notifications:', error);
                alert('Failed to enable notifications: ' + error.message);
            } finally {
                this.isLoading = false;
            }
        },

        async unsubscribe() {
            if (!this.isSupported) return;

            this.isLoading = true;

            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    // Unsubscribe from push notifications
                    await subscription.unsubscribe();

                    // Notify server
                    await fetch('{{ route('push.unsubscribe') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ endpoint: subscription.endpoint })
                    });

                    this.isSubscribed = false;
                    console.log('Push notification unsubscribed');
                }
            } catch (error) {
                console.error('Error unsubscribing from push notifications:', error);
                alert('Failed to disable notifications: ' + error.message);
            } finally {
                this.isLoading = false;
            }
        },

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        }));
    };

    if (window.Alpine && typeof window.Alpine.data === 'function') {
        // Alpine already initialized – register immediately
        register();
    } else {
        // Register on Alpine init in case Alpine loads later
        document.addEventListener('alpine:init', register);
    }
})();
</script>
