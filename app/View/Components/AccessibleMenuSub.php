<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\Contracts\View\View;

class AccessibleMenuSub extends Component
{
    public string $uuid;

    public function __construct(
        public ?string $id = null,
        public ?string $title = null,
        public ?string $icon = null,
        public ?string $iconClasses = null,
        public bool $open = false,
        public ?bool $hidden = false,
        public ?bool $disabled = false,
    ) {
        $this->uuid = 'accessible-menu-sub-' . md5(serialize($this)) . $id;
    }

    public function render(): View|string
    {
        if ($this->hidden === true) {
            return '';
        }

        return view('components.accessible-menu-sub');
    }
}
