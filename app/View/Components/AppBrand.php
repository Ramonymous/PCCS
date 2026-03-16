<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppBrand extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return <<<'HTML'
                <a href="/" wire:navigate class="group">
                    <!-- Hidden when collapsed -->
                    <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
                        <div class="flex items-center gap-3 w-fit p-2 rounded-lg transition-all duration-200 hover:bg-base-200">
                            <div class="relative">
                                <x-icon name="o-cube" class="w-8 h-8 text-primary drop-shadow-lg" />
                                <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-accent rounded-full border-2 border-base-100"></div>
                            </div>
                            <div class="flex flex-col -space-y-1">
                                <span class="font-extrabold text-2xl tracking-tight bg-gradient-to-r from-primary via-secondary to-accent bg-clip-text text-transparent">
                                    {{ env('APP_NAME', 'SIMS') }}
                                </span>
                                <span class="text-[10px] font-medium text-base-content/60 tracking-wide uppercase">
                                    {{ env('PLANT_NAME', 'ASI Plant 2') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Display when collapsed -->
                    <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-[32px]">
                        <div class="relative">
                            <x-icon name="s-cube" class="w-8 h-8 text-primary drop-shadow-lg" />
                            <div class="absolute -bottom-1 -right-1 w-2.5 h-2.5 bg-accent rounded-full border-2 border-base-100"></div>
                        </div>
                    </div>
                </a>
            HTML;
    }
}
