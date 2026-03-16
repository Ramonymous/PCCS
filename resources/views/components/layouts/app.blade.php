<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen font-sans antialiased bg-gradient-to-br from-base-200 via-base-100 to-base-200">

    {{-- Mobile Navigation --}}
    <x-nav sticky class="lg:hidden backdrop-blur-md bg-base-100/80 border-b border-base-300 shadow-sm">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer hover:text-primary transition-colors" />
            </label>
        </x-slot:actions>
    </x-nav>

    <x-main>
        {{-- Sidebar with improved styling --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-base-100 border-r border-base-300 shadow-xl lg:shadow-none">

            {{-- Brand Section --}}
            <div class="sticky top-0 z-10 bg-base-100 border-b border-base-300/50 pb-3">
                <x-app-brand class="px-5 pt-5" />
            </div>

            {{-- Navigation Menu --}}
            <x-menu activate-by-route class="px-2 mt-2">
                @if($user = auth()->user())

                
                <x-partials.menu />

                {{-- User Profile Section --}}
                
                    <x-menu-separator class="my-4" />

                    <div class="px-3 py-2 mb-4">
                        <div class="bg-gradient-to-br from-primary/10 via-secondary/10 to-accent/10 rounded-xl p-3 border border-base-300 shadow-sm hover:shadow-md transition-all duration-200">
                            <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="!p-0">
                                <x-slot:avatar>
                                    <x-avatar
                                        class="w-10 h-10"
                                        alt="{{ $user->name }}"
                                        placeholder="{{ strtoupper(substr($user->name, 0, 2)) }}"
                                    />
                                </x-slot:avatar>
                                
                                <x-slot:value>
                                    <div class="font-semibold text-sm text-base-content">{{ $user->name }}</div>
                                </x-slot:value>
                                
                                <x-slot:sub-value>
                                    <div class="text-xs text-base-content/60 truncate">{{ $user->email }}</div>
                                </x-slot:sub-value>
                                
                                <x-slot:actions>
                                    <x-accessible-dropdown>
                                        <x-slot:trigger>
                                            <x-button icon="o-ellipsis-vertical" class="btn-circle btn-ghost btn-sm" />
                                        </x-slot:trigger>
                                        <x-menu-item :title="__('Profile')" icon="o-user" link="{{ route('profile.index') }}" />
                                        <x-menu-item :title="__('2FA Setup')" icon="o-shield-check" link="{{ route('2fa.setup') }}" />
                                        <x-menu-separator />
                                        <x-menu-item :title="__('Logout')" icon="o-power" link="/logout" no-wire-navigate />
                                    </x-accessible-dropdown>
                                    <x-theme-toggle 
                                        darkTheme="dim" 
                                        lightTheme="winter" 
                                        class="btn-ghost btn-sm btn-circle hover:bg-base-200"
                                    />
                                </x-slot:actions>
                            </x-list-item>
                        </div>
                    </div>

                    {{-- Language Switcher --}}
                    <livewire:components.ui.locale-switcher />
                @endif
                
            </x-menu>
        </x-slot:sidebar>

        {{-- Main Content Area --}}
        <x-slot:content class="p-4 lg:p-6">
            <div class="max-w-[1600px] mx-auto">
                {{ $slot }}
            </div>
        </x-slot:content>
    </x-main>

    <x-toast />
    
    {{-- Global Loading Modal --}}
    <x-loading-modal />
    
    @livewireScripts
    @stack('scripts')     

</body>
</html>