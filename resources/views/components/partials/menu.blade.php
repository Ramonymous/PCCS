<div class="space-y-1">
    {{-- Dashboard --}}
    <x-menu-item 
        :title="__('Dashboard')" 
        icon="o-home" 
        link="{{ route('dashboard') }}" 
        route="dashboard"
        class="hover:bg-primary/10 hover:text-primary transition-all duration-200"
    />
    
    <div class="my-2"></div>

    {{-- Traceability Section --}}
    <x-accessible-menu-sub :title="__('Traceability')" icon="o-qr-code" class="font-semibold">
        <x-menu-item 
            :title="__('PCCs')" 
            icon="o-identification" 
            link="{{ route('trace.pcc') }}" 
            route="trace.pcc"
            class="hover:bg-primary/10 transition-colors"
        />
        <x-menu-item 
            :title="__('PCC Report')" 
            icon="o-archive-box" 
            link="{{ route('trace.pcc-report') }}" 
            route="trace.pcc-report"
            class="hover:bg-primary/10 transition-colors"
        />
    </x-accessible-menu-sub>

    {{-- PPIC Section --}}
    <x-accessible-menu-sub :title="__('PPIC')" icon="o-clipboard-document-list" class="font-semibold">
        <x-menu-item 
            :title="__('HPM Dashboard')" 
            icon="o-chart-bar" 
            link="{{ route('ppic.hpm.dashboard') }}" 
            route="ppic.hpm.dashboard"
            class="hover:bg-secondary/10 transition-colors"
        />
        @if(auth()->user()->hasAnyPermission(['manage', 'manage_pcc']))
        <x-menu-item 
            :title="__('PCCs')" 
            icon="o-document-text" 
            link="{{ route('ppic.hpm.pccs') }}" 
            route="ppic.hpm.pccs"
            class="hover:bg-secondary/10 transition-colors"
        />
        @endif
        <x-menu-item 
            :title="__('Schedules')" 
            icon="o-calendar-days" 
            link="{{ route('ppic.hpm.schedules') }}" 
            route="ppic.hpm.schedules"
            class="hover:bg-secondary/10 transition-colors"
        />
        @if(auth()->user()->hasAnyPermission(['manage', 'receive_hpm']))
        <x-menu-separator />
        <x-menu-item 
            :title="__('Received Scanner')" 
            icon="o-inbox-arrow-down" 
            link="{{ route('ppic.hpm.received') }}" 
            route="ppic.hpm.received"
            class="hover:bg-success/10 transition-colors"
        />
        @endif
        @if(auth()->user()->hasAnyPermission(['manage', 'delivery_hpm']))
        <x-menu-item 
            :title="__('Delivery Scanner')" 
            icon="o-truck" 
            link="{{ route('ppic.hpm.delivery') }}" 
            route="ppic.hpm.delivery"
            class="hover:bg-success/10 transition-colors"
        />
        @endif
    </x-accessible-menu-sub>

    {{-- Production Section --}}
    @hasanyrole('admin|weld')
    <x-accessible-menu-sub :title="__('Production')" icon="o-wrench-screwdriver" class="font-semibold">
        <x-menu-item 
            :title="__('Weld Scanner')" 
            icon="o-fire" 
            link="{{ route('weld.hpm.check') }}" 
            route="weld.hpm.check"
            class="hover:bg-warning/10 transition-colors"
        />
    </x-accessible-menu-sub>
    @endhasanyrole
    
    {{-- Quality Section --}}
    @hasanyrole('admin|quality')
    <x-accessible-menu-sub :title="__('Quality')" icon="o-shield-check" class="font-semibold">
        <x-menu-item 
            :title="__('PDI Scanner')" 
            icon="o-magnifying-glass-circle" 
            link="{{ route('qa.hpm.check') }}" 
            route="qa.hpm.check"
            class="hover:bg-info/10 transition-colors"
        />
        <x-menu-item 
            :title="__('HPM Checkpoint')" 
            icon="o-check-badge" 
            link="{{ route('qa.hpm.ccp.index') }}" 
            route="qa.hpm.ccp.index"
            class="hover:bg-info/10 transition-colors"
        />
    </x-accessible-menu-sub>
    @endhasanyrole
    
    {{-- Master Data Section --}}
    @hasrole('admin')
    <x-menu-separator class="my-4" />
    <x-accessible-menu-sub :title="__('Master Data')" icon="o-circle-stack" class="font-semibold">
        <x-menu-item 
            :title="__('Users')" 
            icon="o-user-group" 
            link="{{ route('manage.users.index') }}" 
            route="manage.users.index"
            class="hover:bg-accent/10 transition-colors"
        />
        <x-menu-item 
            :title="__('Customers')" 
            icon="o-building-office" 
            link="{{ route('manage.customers.index') }}" 
            route="manage.customers.index"
            class="hover:bg-accent/10 transition-colors"
        />
        <x-menu-item 
            :title="__('Suppliers')" 
            icon="o-building-storefront" 
            link="{{ route('manage.suppliers.index') }}" 
            route="manage.suppliers.index"
            class="hover:bg-accent/10 transition-colors"
        />
        <x-menu-item 
            :title="__('Finish Goods')" 
            icon="o-cube" 
            link="{{ route('manage.finish_goods.index') }}" 
            route="manage.finish_goods.index"
            class="hover:bg-accent/10 transition-colors"
        />
    </x-accessible-menu-sub>
    @endhasrole

    {{-- Scanner Locks (Supervisors & Admins) --}}
    @if(auth()->user()->hasAnyPermission(['weld.unlock-scanner', 'qa.unlock-scanner', 'delivery.unlock-scanner']) || auth()->user()->hasRole('admin'))
    @php
        $scannerLockCount = App\Models\ScannerLock::where('locked_until', '>', now())->count();
    @endphp
    <x-menu-item 
        :title="__('Scanner Locks') . ($scannerLockCount > 0 ? ' (' . $scannerLockCount . ')' : '')" 
        icon="o-lock-closed" 
        link="{{ route('manage.scanner-locks') }}" 
        route="manage.scanner-locks"
        class="hover:bg-error/10 transition-colors"
    />
    @endif
</div>