@aware(['activeBgColor' => 'bg-base-300'])

@php
    $submenuActive = Str::contains($slot, 'mary-active-menu');
@endphp

<li @class(['menu-disabled' => $disabled])>
    <details
        x-data="{ open: @js($submenuActive || $open) }"
        x-init="$watch('open', value => $el.open = value)"
        :open="open"
    >
        <summary
            @click.prevent="open = !open"
            class="hover:text-inherit px-4 py-1.5 my-0.5 text-inherit @if($submenuActive) {{ $activeBgColor }} @endif"
        >
            @if($icon)
                <x-mary-icon :name="$icon" @class(['inline-flex my-0.5', $iconClasses]) />
            @endif
            <span class="mary-hideable whitespace-nowrap truncate">{{ $title }}</span>
        </summary>
        <ul class="mary-hideable">
            {{ $slot }}
        </ul>
    </details>
</li>
