<div
    x-data="{open:false}"
    @click.outside="open=false"
    class="dropdown dropdown-end"
>
    @if(isset($trigger))
        <div tabindex="0" @click="open=!open" class="cursor-pointer">
            {{ $trigger }}
        </div>
    @else
        <button
            type="button"
            tabindex="0"
            @click="open=!open"
            class="btn"
        >
            {{ $label }}
            <x-mary-icon :name="$icon" />
        </button>
    @endif

    <ul
        x-show="open"
        x-cloak
        x-transition
        @click="open=false"
        tabindex="0"
        class="dropdown-content menu p-2 shadow-lg bg-base-100 rounded-box w-52 z-[100] border border-base-300"
    >
        {{ $slot }}
    </ul>
</div>
