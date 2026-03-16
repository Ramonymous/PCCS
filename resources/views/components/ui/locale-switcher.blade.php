<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\App;

new class extends Component {
    public string $currentLocale;
    
    public array $localeFlags = [
        'en' => 'gb.svg',
        'id' => 'id.svg',
        'ja' => 'ja.svg',
    ];
    
    public function mount(): void
    {
        $this->currentLocale = app()->getLocale();
    }
    
    public function switchLocale(string $locale): void
    {
        $supported = array_keys((array) config('app.supported_locales', []));
        
        if (!in_array($locale, $supported, true)) {
            return;
        }
        
        // Set session
        session()->put('locale', $locale);
        
        // Optional: Update user preference if column exists
        // if (auth()->user() && Schema::hasColumn('users', 'locale')) {
        //     auth()->user()->update(['locale' => $locale]);
        // }
        
        // Set current locale
        App::setLocale($locale);
        $this->currentLocale = $locale;
        
        // Reload page to apply new locale
        $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
    }
}; ?>

<div class="px-3 pb-4">
    <div class="rounded-xl border border-base-300 bg-base-100 p-3">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium text-base-content/70">{{ __('Language') }}</span>
        </div>
        <div class="flex items-center gap-2">
            @foreach(config('app.supported_locales', []) as $code => $name)
                <button
                    wire:click="switchLocale('{{ $code }}')"
                    class="btn btn-xs {{ $currentLocale === $code ? 'btn-primary' : 'btn-ghost' }} gap-2"
                    title="{{ $name }}"
                >
                    @if(isset($localeFlags[$code]))
                        <img 
                            src="{{ asset('images/flags/' . $localeFlags[$code]) }}" 
                            alt="{{ $name }}" 
                            class="w-5 h-4 object-cover rounded-sm"
                        />
                    @endif
                    <span>{{ strtoupper($code) }}</span>
                </button>
            @endforeach
        </div>
    </div>
</div>
