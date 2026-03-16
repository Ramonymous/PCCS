<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
 
new
#[Layout('components.layouts.auth')]
#[Title('Login')]
class extends Component {
 
    #[Rule('required|email')]
    public string $email = '';
 
    #[Rule('required')]
    public string $password = '';
    
    // Remember me flag (optional)
    public bool $remember = false;
 
    public function mount()
    {
        // It is logged in
        if (auth()->user()) {
            return redirect('/');
        }
    }
 
    public function login()
    {
        $credentials = $this->validate();
 
        if (auth()->attempt($credentials, $this->remember)) {
            request()->session()->regenerate();
 
            return redirect()->intended('/')->with('success', 'You are logged in successfully!');
        }
 
        $this->addError('email', 'The provided credentials do not match our records.');
    }
};?>

<div class="w-full max-w-md px-4">
    <div class="w-full">
        {{-- Login Card --}}
        <div class="neon-card-wrapper cyber-slide-up">
            <div class="neon-card relative overflow-hidden">
                {{-- Animated Border --}}
                <div class="absolute inset-0 rounded-2xl border-2 border-transparent animated-border"></div>
                
                {{-- Card Glow Effect --}}
                <div class="absolute -inset-1 bg-gradient-to-r from-cyan-500 via-purple-500 to-pink-500 rounded-2xl blur opacity-20 group-hover:opacity-30 transition duration-1000"></div>
                
                {{-- Card Content --}}
                <div class="relative bg-gray-950/90 backdrop-blur-xl rounded-2xl p-8 border border-cyan-500/30">
                    {{-- Header with Scan Line Effect --}}
                    <div class="relative mb-6">
                        <div class="scan-line-header"></div>
                        <h2 class="text-3xl font-black text-center text-cyan-400 glow-text tracking-wider uppercase">
                            <x-icon name="o-lock-closed" class="w-7 h-7 inline-block mb-1" />
                            System Login
                        </h2>
                        <div class="h-px bg-gradient-to-r from-transparent via-cyan-500 to-transparent mt-4 cyber-line"></div>
                    </div>

                    <x-form wire:submit="login" aria-describedby="login-status" aria-live="polite">
                        <div class="space-y-6">
                            {{-- Email Input --}}
                            <div class="input-cyber-wrapper">
                                <label class="block text-cyan-400 text-xs font-bold mb-2 tracking-widest uppercase">
                                    <x-icon name="o-envelope" class="w-4 h-4 inline-block" /> Email Address
                                </label>
                                <x-input 
                                    id="email"
                                    placeholder="user@system.terminal" 
                                    wire:model.lazy="email" 
                                    type="email"
                                    autocomplete="email"
                                    autofocus
                                    class="input-cyber bg-gray-900/50 border-2 border-cyan-500/30 focus:border-cyan-400 text-cyan-100 placeholder:text-cyan-700 focus:ring-2 focus:ring-cyan-500/50"
                                />
                                @error('email')
                                    <p class="mt-1 text-xs text-pink-400">{{ $message }}</p>
                                @enderror
                                <div class="input-cyber-glow"></div>
                            </div>

                            {{-- Password Input --}}
                            <div class="input-cyber-wrapper">
                                <label class="block text-cyan-400 text-xs font-bold mb-2 tracking-widest uppercase">
                                    <x-icon name="o-key" class="w-4 h-4 inline-block" /> Access Code
                                </label>
                                <x-input 
                                    id="password"
                                    placeholder="••••••••••••" 
                                    wire:model.lazy="password" 
                                    type="password" 
                                    autocomplete="current-password"
                                    class="input-cyber bg-gray-900/50 border-2 border-cyan-500/30 focus:border-cyan-400 text-cyan-100 placeholder:text-cyan-700 focus:ring-2 focus:ring-cyan-500/50"
                                />
                                @error('password')
                                    <p class="mt-1 text-xs text-pink-400">{{ $message }}</p>
                                @enderror
                                <div class="input-cyber-glow"></div>
                            </div>

                            {{-- Remember & Forgot --}}
                            <div class="flex items-center justify-between text-sm pt-2">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" wire:model="remember" aria-label="Remember Session" class="checkbox checkbox-sm border-2 border-cyan-500/50 [--chkbg:theme(colors.cyan.500)] [--chkfg:theme(colors.gray.950)] checked:border-cyan-400" />
                                    <span class="text-cyan-300 group-hover:text-cyan-400 transition-colors tracking-wide">Remember Session</span>
                                </label>
                                <a href="#" class="text-purple-400 hover:text-purple-300 font-semibold tracking-wide hover:glow-text-sm transition-all">
                                    Reset Access →
                                </a>
                            </div>
                        </div>

                        <x-slot:actions>
                            <div class="w-full space-y-4 mt-8">
                                {{-- Login Button --}}
                                <button 
                                    type="submit" 
                                    class="cyber-button w-full group"
                                    wire:loading.attr="disabled"
                                >
                                    <span class="relative z-10 flex items-center justify-center gap-2 font-bold text-lg tracking-wider uppercase">
                                        <x-icon name="o-arrow-right-on-rectangle" class="w-5 h-5" />
                                        <span wire:loading.remove wire:target="login">Initialize Access</span>
                                        <span wire:loading wire:target="login" class="flex items-center gap-2">
                                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Authenticating...
                                        </span>
                                    </span>
                                    <div class="cyber-button-glow"></div>
                                </button>
                            </div>
                        </x-slot:actions>
                    </x-form>

                    {{-- Live region for status messages --}}
                    <p id="login-status" class="sr-only" aria-live="polite"></p>

                    {{-- Status Indicator --}}
                    <div class="flex items-center justify-center gap-2 mt-6 text-xs">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse shadow-[0_0_10px_rgba(74,222,128,0.8)]"></div>
                        <span class="text-green-400 font-mono">SYSTEM ONLINE</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="text-center mt-8 space-y-2 cyber-fade-in-delay">
            <p class="text-cyan-600 text-xs font-mono tracking-wider">
                © {{ date('Y') }} {{ config('app.name') }} • SECURE TERMINAL v2.5.1
            </p>
            <div class="flex items-center justify-center gap-4 text-xs text-cyan-700">
                <span class="hover:text-cyan-500 cursor-pointer transition-colors">Privacy Protocol</span>
                <span class="text-cyan-900">•</span>
                <span class="hover:text-cyan-500 cursor-pointer transition-colors">Terms of Access</span>
                <span class="text-cyan-900">•</span>
                <span class="hover:text-cyan-500 cursor-pointer transition-colors">System Status</span>
            </div>
        </div>
    </div>
</div>

@pushOnce('styles')
<style>
    /* Cyberpunk Animations */
    @keyframes cyber-fade-in {
        from { 
            opacity: 0; 
            transform: translateY(-20px);
            filter: blur(10px);
        }
        to { 
            opacity: 1; 
            transform: translateY(0);
            filter: blur(0);
        }
    }
    
    @keyframes cyber-slide-up {
        from { 
            opacity: 0; 
            transform: translateY(30px) scale(0.95);
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes cyber-float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    
    @keyframes border-dance {
        0%, 100% { 
            border-color: rgba(0, 255, 255, 0.3);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
        }
        33% { 
            border-color: rgba(139, 92, 246, 0.3);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
        }
        66% { 
            border-color: rgba(236, 72, 153, 0.3);
            box-shadow: 0 0 20px rgba(236, 72, 153, 0.2);
        }
    }
    
    @keyframes scan-line {
        0% { transform: translateY(-100%); opacity: 0; }
        50% { opacity: 1; }
        100% { transform: translateY(100%); opacity: 0; }
    }
    
    @keyframes glow-pulse {
        0%, 100% { opacity: 0.3; }
        50% { opacity: 0.6; }
    }
    
    @keyframes glitch-text {
        0% { text-shadow: 2px 0 rgba(0, 255, 255, 0.7), -2px 0 rgba(255, 0, 255, 0.7); }
        25% { text-shadow: -2px 0 rgba(0, 255, 255, 0.7), 2px 0 rgba(255, 0, 255, 0.7); }
        50% { text-shadow: 2px 0 rgba(255, 0, 255, 0.7), -2px 0 rgba(0, 255, 255, 0.7); }
        75% { text-shadow: -2px 0 rgba(255, 0, 255, 0.7), 2px 0 rgba(0, 255, 255, 0.7); }
        100% { text-shadow: 2px 0 rgba(0, 255, 255, 0.7), -2px 0 rgba(255, 0, 255, 0.7); }
    }

    .cyber-fade-in {
        animation: cyber-fade-in 0.8s ease-out;
    }
    
    .cyber-fade-in-delay {
        animation: cyber-fade-in 0.8s ease-out 0.4s both;
    }
    
    .cyber-slide-up {
        animation: cyber-slide-up 0.8s ease-out 0.2s both;
    }
    
    .cyber-float {
        animation: cyber-float 3s ease-in-out infinite;
    }
    
    .cyber-glitch {
        position: relative;
        animation: glitch-text 3s infinite;
    }
    
    .cyber-line {
        animation: glow-pulse 2s ease-in-out infinite;
    }
    
    .animated-border {
        animation: border-dance 4s ease-in-out infinite;
    }
    
    .scan-line-header {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.8), transparent);
        animation: scan-line 3s linear infinite;
    }
    
    .glow-text {
        text-shadow: 
            0 0 10px rgba(0, 255, 255, 0.5),
            0 0 20px rgba(0, 255, 255, 0.3);
    }
    
    .glow-text-strong {
        text-shadow: 
            0 0 10px rgba(0, 255, 255, 0.8),
            0 0 20px rgba(0, 255, 255, 0.6),
            0 0 30px rgba(0, 255, 255, 0.4);
    }
    
    .glow-text-sm {
        text-shadow: 0 0 5px currentColor;
    }
    
    .animate-pulse-slow {
        animation: glow-pulse 3s ease-in-out infinite;
    }
    
    /* Cyber Button */
    .cyber-button {
        position: relative;
        background: linear-gradient(135deg, rgba(0, 255, 255, 0.1), rgba(139, 92, 246, 0.1));
        border: 2px solid rgba(0, 255, 255, 0.5);
        color: #0ff;
        padding: 1rem 2rem;
        border-radius: 0.75rem;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .cyber-button:hover:not(:disabled) {
        border-color: rgba(0, 255, 255, 0.8);
        box-shadow: 
            0 0 20px rgba(0, 255, 255, 0.4),
            inset 0 0 20px rgba(0, 255, 255, 0.1);
        transform: translateY(-2px);
    }
    
    .cyber-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .cyber-button-glow {
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, transparent, rgba(0, 255, 255, 0.2));
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .cyber-button:hover:not(:disabled) .cyber-button-glow {
        opacity: 1;
    }
    
    /* Cyber Icon Button */
    .cyber-icon-btn {
        position: relative;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 255, 255, 0.05);
        border: 2px solid rgba(0, 255, 255, 0.3);
        border-radius: 0.5rem;
        color: rgba(0, 255, 255, 0.7);
        transition: all 0.3s ease;
    }
    
    .cyber-icon-btn:hover {
        background: rgba(0, 255, 255, 0.1);
        border-color: rgba(0, 255, 255, 0.6);
        color: #0ff;
        box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
        transform: translateY(-2px);
    }
    
    /* Input Cyber */
    .input-cyber-wrapper {
        position: relative;
    }
    
    .input-cyber {
        font-family: 'Courier New', monospace;
        transition: all 0.3s ease;
    }
    
    .input-cyber:focus {
        box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
    }
    
    .input-cyber-glow {
        position: absolute;
        inset: 0;
        border-radius: 0.5rem;
        background: linear-gradient(135deg, rgba(0, 255, 255, 0.1), rgba(139, 92, 246, 0.1));
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }
    
    .input-cyber-wrapper:focus-within .input-cyber-glow {
        opacity: 1;
    }
</style>
@endPushOnce