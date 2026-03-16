<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Cache;
use App\Models\Customer\HPM\Pcc;
use App\Models\Customer\HPM\Schedule;

new
#[Title('Dashboard')]
class extends Component {
    public string $today;
    public string $greeting;
    public string $roleMessage;

    public function mount(): void
    {
        $this->today = now()->toDateString();
        $this->setGreeting();
        $this->setRoleMessage();
    }

    public function setGreeting(): void
    {
        $hour = now()->hour;
        
        if ($hour < 12) {
            $this->greeting = __('Good Morning');
        } elseif ($hour < 15) {
            $this->greeting = __('Good Afternoon');
        } elseif ($hour < 18) {
            $this->greeting = __('Good Evening');
        } else {
            $this->greeting = __('Good Night');
        }
    }

    public function setRoleMessage(): void
    {
        $user = auth()->user();
        
        if ($user->hasRole('admin')) {
            $this->roleMessage = __('You have full access to all system features. Monitor and manage entire PCCS operations easily.');
        } elseif ($user->hasRole('weld')) {
            $this->roleMessage = __('Access welding production scanner for verification and quality control of the welding process.');
        } elseif ($user->hasRole('quality')) {
            $this->roleMessage = __('Perform PDI checks and manage critical control points to ensure product quality is maintained.');
        } elseif ($user->hasAnyPermission(['manage_pcc', 'receive_hpm', 'delivery_hpm'])) {
            $this->roleMessage = __('Manage production cards, material receipt, and product delivery with an integrated system.');
        } else {
            $this->roleMessage = __('Welcome to the Production Card Control System.');
        }
    }

    #[Computed]
    public function metrics(): array
    {
        $key = "dashboard_metrics_" . now()->format('Y-m-d');

        return Cache::remember($key, 300, function () {
            $totalPcc = (int) Pcc::count();
            
            $todayBySchedule = (int) Pcc::whereHas('schedule', function ($q) {
                $q->whereDate('adjusted_date', $this->today)
                  ->orWhereDate('schedule_date', $this->today);
            })->count();

            $todayNoSchedule = (int) Pcc::whereDoesntHave('schedule')
                ->whereDate('date', $this->today)
                ->count();

            $todayPcc = $todayBySchedule + $todayNoSchedule;

            $schedulesToday = (int) Schedule::whereDate('adjusted_date', $this->today)
                ->orWhereDate('schedule_date', $this->today)
                ->count();

            // Monthly metrics
            $monthlyPcc = (int) Pcc::whereYear('date', now()->year)
                ->whereMonth('date', now()->month)
                ->count();

            $monthlySchedules = (int) Schedule::where(function ($q) {
                $q->whereYear('adjusted_date', now()->year)
                  ->whereMonth('adjusted_date', now()->month);
            })->orWhere(function ($q) {
                $q->whereYear('schedule_date', now()->year)
                  ->whereMonth('schedule_date', now()->month);
            })->count();

            return compact('totalPcc', 'todayPcc', 'schedulesToday', 'monthlyPcc', 'monthlySchedules');
        });
    }

    #[Computed]
    public function headers(): array
    {
        // Static data — long TTL is fine
        return [
            ['key' => 'effective_date', 'label' => __('Date')],
            ['key' => 'part_no', 'label' => __('Part No')],
            ['key' => 'part_name', 'label' => __('Part Name')],
            ['key' => 'slip_no', 'label' => __('Slip No')],
            ['key' => 'slip_barcode', 'label' => 'Barcode'],
        ];
    }

    #[Computed]
    public function recentPccs()
    {
        $key = "dashboard_recent_{$this->today}";

        return Cache::remember($key, 300, function () {
            return Pcc::select([
                'id',
                'part_no',
                'part_name',
                'slip_no',
                'slip_barcode',
                'date',
                'time',
            ])
                ->with('schedule:id,slip_number,schedule_date,adjusted_date')
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc')
                ->limit(10)
                ->get()
                ->map(function (Pcc $p) {
                    // Add an effective_date field for table display
                    $p->effective_date_display = optional($p->effective_date)->format('Y-m-d');
                    return $p;
                });
        });
    }

    public function with(): array
    {
        return [
            'metrics' => $this->metrics,
            'headers' => $this->headers,
            'recent' => $this->recentPccs,
            'today' => $this->today,
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Welcome Header --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary/10 via-secondary/10 to-accent/10 p-8 shadow-lg border border-base-300">
        <div class="relative z-10">
            <div class="flex items-start justify-between">
                <div class="space-y-2">
                    <h1 class="text-4xl font-bold text-base-content">
                        {{ $greeting }}, {{ auth()->user()->name }}! 👋
                    </h1>
                    <p class="text-lg text-base-content/70 max-w-2xl">
                        {{ $roleMessage }}
                    </p>
                    <div class="flex items-center gap-2 mt-4">
                        <x-icon name="o-calendar" class="w-5 h-5 text-primary" />
                        <span class="text-sm font-medium text-base-content/60">
                            {{ now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}
                        </span>
                    </div>
                </div>
                <div class="hidden lg:flex items-center gap-2">
                    <x-button icon="o-arrow-path" class="btn-ghost" wire:click="$refresh" :tooltip="__('Refresh data')" />
                </div>
            </div>
        </div>
        {{-- Decorative background elements --}}
        <div class="absolute top-0 right-0 -mt-12 -mr-12 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -mb-12 -ml-12 w-64 h-64 bg-secondary/5 rounded-full blur-3xl"></div>
    </div>

    {{-- Statistics Overview --}}
    <div>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
            <x-icon name="o-chart-bar-square" class="w-6 h-6 text-primary" />
            {{ __("Today's Summary") }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card class="bg-gradient-to-br from-primary/5 to-primary/10 shadow-md border border-primary/20 hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-base-content/60">{{ __('Total PCC') }}</div>
                        <div class="text-3xl font-extrabold text-primary">{{ number_format($metrics['totalPcc']) }}</div>
                        <div class="text-xs text-base-content/50 mt-1">{{ __('All cards total') }}</div>
                    </div>
                    <div class="p-3 bg-primary/10 rounded-xl">
                        <x-icon name="o-qr-code" class="w-10 h-10 text-primary" />
                    </div>
                </div>
            </x-card>

            <x-card class="bg-gradient-to-br from-secondary/5 to-secondary/10 shadow-md border border-secondary/20 hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-base-content/60">{{ __('PCC Today') }}</div>
                        <div class="text-3xl font-extrabold text-secondary">{{ number_format($metrics['todayPcc']) }}</div>
                        <div class="text-xs text-base-content/50 mt-1">{{ __('Schedule') }}: {{ number_format($metrics['schedulesToday']) }}</div>
                    </div>
                    <div class="p-3 bg-secondary/10 rounded-xl">
                        <x-icon name="o-calendar-days" class="w-10 h-10 text-secondary" />
                    </div>
                </div>
            </x-card>

            <x-card class="bg-gradient-to-br from-success/5 to-success/10 shadow-md border border-success/20 hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-base-content/60">{{ __('PCCs This Month') }}</div>
                        <div class="text-3xl font-extrabold text-success">{{ number_format($metrics['monthlyPcc']) }}</div>
                        <div class="text-xs text-base-content/50 mt-1">{{ now()->locale('id')->isoFormat('MMMM YYYY') }}</div>
                    </div>
                    <div class="p-3 bg-success/10 rounded-xl">
                        <x-icon name="o-chart-bar" class="w-10 h-10 text-success" />
                    </div>
                </div>
            </x-card>

            <x-card class="bg-gradient-to-br from-warning/5 to-warning/10 shadow-md border border-warning/20 hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-base-content/60">{{ __('Schedules This Month') }}</div>
                        <div class="text-3xl font-extrabold text-warning">{{ number_format($metrics['monthlySchedules']) }}</div>
                        <div class="text-xs text-base-content/50 mt-1">{{ __('Total schedules this month') }}</div>
                    </div>
                    <div class="p-3 bg-warning/10 rounded-xl">
                        <x-icon name="o-calendar" class="w-10 h-10 text-warning" />
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Quick Access Menu --}}
    <div>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
            <x-icon name="o-rocket-launch" class="w-6 h-6 text-secondary" />
            {{ __('Quick Access') }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {{-- Traceability --}}
            <x-card class="bg-base-100 shadow-md border border-base-300 hover:shadow-xl hover:border-primary/50 transition-all cursor-pointer group" 
                    link="{{ route('trace.pcc') }}" wire:navigate>
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-primary/10 rounded-xl group-hover:bg-primary/20 transition-colors">
                        <x-icon name="o-qr-code" class="w-8 h-8 text-primary" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg group-hover:text-primary transition-colors">{{ __('Traceability') }}</h3>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('Track PCC status and history') }}</p>
                    </div>
                    <x-icon name="o-arrow-right" class="w-5 h-5 text-base-content/30 group-hover:text-primary group-hover:translate-x-1 transition-all" />
                </div>
            </x-card>

            @if(auth()->user()->hasAnyPermission(['manage', 'manage_pcc']))
            {{-- PCCs Management --}}
            <x-card class="bg-base-100 shadow-md border border-base-300 hover:shadow-xl hover:border-secondary/50 transition-all cursor-pointer group" 
                    link="{{ route('ppic.hpm.pccs') }}" wire:navigate>
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-secondary/10 rounded-xl group-hover:bg-secondary/20 transition-colors">
                        <x-icon name="o-document-text" class="w-8 h-8 text-secondary" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg group-hover:text-secondary transition-colors">{{ __('PCCs') }}</h3>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('Manage PCC') }}</p>
                    </div>
                    <x-icon name="o-arrow-right" class="w-5 h-5 text-base-content/30 group-hover:text-secondary group-hover:translate-x-1 transition-all" />
                </div>
            </x-card>
            @endif

            {{-- Schedules --}}
            <x-card class="bg-base-100 shadow-md border border-base-300 hover:shadow-xl hover:border-accent/50 transition-all cursor-pointer group" 
                    link="{{ route('ppic.hpm.schedules') }}" wire:navigate>
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-accent/10 rounded-xl group-hover:bg-accent/20 transition-colors">
                        <x-icon name="o-calendar-days" class="w-8 h-8 text-accent" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg group-hover:text-accent transition-colors">{{ __('Schedules') }}</h3>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('Daily delivery schedule') }}</p>
                    </div>
                    <x-icon name="o-arrow-right" class="w-5 h-5 text-base-content/30 group-hover:text-accent group-hover:translate-x-1 transition-all" />
                </div>
            </x-card>

            @if(auth()->user()->hasAnyPermission(['manage', 'receive_hpm']))
            {{-- Receiving --}}
            <x-card class="bg-base-100 shadow-md border border-base-300 hover:shadow-xl hover:border-info/50 transition-all cursor-pointer group" 
                    link="{{ route('ppic.hpm.received') }}" wire:navigate>
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-info/10 rounded-xl group-hover:bg-info/20 transition-colors">
                        <x-icon name="o-inbox-arrow-down" class="w-8 h-8 text-info" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg group-hover:text-info transition-colors">{{ __('Receiving') }}</h3>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('Goods receiving scanner') }}</p>
                    </div>
                    <x-icon name="o-arrow-right" class="w-5 h-5 text-base-content/30 group-hover:text-info group-hover:translate-x-1 transition-all" />
                </div>
            </x-card>
            @endif

            @if(auth()->user()->hasAnyPermission(['manage', 'delivery_hpm']))
            {{-- Delivery --}}
            <x-card class="bg-base-100 shadow-md border border-base-300 hover:shadow-xl hover:border-success/50 transition-all cursor-pointer group" 
                    link="{{ route('ppic.hpm.delivery') }}" wire:navigate>
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-success/10 rounded-xl group-hover:bg-success/20 transition-colors">
                        <x-icon name="o-truck" class="w-8 h-8 text-success" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg group-hover:text-success transition-colors">{{ __('Delivery') }}</h3>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('Product delivery scanner') }}</p>
                    </div>
                    <x-icon name="o-arrow-right" class="w-5 h-5 text-base-content/30 group-hover:text-success group-hover:translate-x-1 transition-all" />
                </div>
            </x-card>
            @endif

            @hasanyrole('admin|weld')
            {{-- Weld Scanner --}}
            <x-card class="bg-base-100 shadow-md border border-base-300 hover:shadow-xl hover:border-warning/50 transition-all cursor-pointer group" 
                    link="{{ route('weld.hpm.check') }}" wire:navigate>
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-warning/10 rounded-xl group-hover:bg-warning/20 transition-colors">
                        <x-icon name="o-fire" class="w-8 h-8 text-warning" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg group-hover:text-warning transition-colors">{{ __('Weld Check') }}</h3>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('Welding production scanner') }}</p>
                    </div>
                    <x-icon name="o-arrow-right" class="w-5 h-5 text-base-content/30 group-hover:text-warning group-hover:translate-x-1 transition-all" />
                </div>
            </x-card>
            @endhasanyrole

            @hasanyrole('admin|quality')
            {{-- QA Scanner --}}
            <x-card class="bg-base-100 shadow-md border border-base-300 hover:shadow-xl hover:border-error/50 transition-all cursor-pointer group" 
                    link="{{ route('qa.hpm.check') }}" wire:navigate>
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-error/10 rounded-xl group-hover:bg-error/20 transition-colors">
                        <x-icon name="o-shield-check" class="w-8 h-8 text-error" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-lg group-hover:text-error transition-colors">{{ __('QA Check') }}</h3>
                        <p class="text-sm text-base-content/60 mt-1">{{ __('Quality assurance scanner') }}</p>
                    </div>
                    <x-icon name="o-arrow-right" class="w-5 h-5 text-base-content/30 group-hover:text-error group-hover:translate-x-1 transition-all" />
                </div>
            </x-card>
            @endhasanyrole
        </div>
    </div>

    {{-- Recent Activity --}}
    <div>
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
            <x-icon name="o-clock" class="w-6 h-6 text-info" />
            {{ __('Recent Activity') }}
        </h2>
        <x-card class="bg-base-100 shadow-md border border-base-300">
            <x-slot:title>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-lg font-semibold">{{ __('Latest PCC') }}</span>
                        <p class="text-sm text-base-content/60 font-normal mt-1">{{ __('Last 10 PCCs by date/time') }}</p>
                    </div>
                    <x-button icon="o-arrow-right" link="{{ route('ppic.hpm.pccs') }}" wire:navigate 
                              class="btn-ghost btn-sm gap-1" :label="__('View All')" />
                </div>
            </x-slot:title>
            
            @if($recent->isEmpty())
                <div class="text-center py-12">
                    <x-icon name="o-document-text" class="w-16 h-16 text-base-content/20 mx-auto mb-3" />
                    <p class="text-base-content/60">{{ __('No production card data yet') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <x-table :headers="$headers" :rows="$recent" class="table-zebra">
                        @scope('cell_effective_date', $row)
                            <div class="badge badge-ghost gap-2">
                                <x-icon name="o-calendar" class="w-3 h-3" />
                                {{ $row->effective_date_display }}
                            </div>
                        @endscope

                        @scope('cell_part_no', $row)
                            <div class="font-semibold text-primary">{{ $row->part_no }}</div>
                        @endscope

                        @scope('cell_part_name', $row)
                            <div class="truncate max-w-[320px]" title="{{ $row->part_name }}">{{ $row->part_name }}</div>
                        @endscope

                        @scope('cell_slip_no', $row)
                            <div class="inline-flex items-center gap-1.5 font-mono text-sm">
                                <x-icon name="o-hashtag" class="w-4 h-4 text-secondary" />
                                <span class="font-semibold">{{ $row->slip_no }}</span>
                            </div>
                        @endscope

                        @scope('cell_slip_barcode', $row)
                            <code class="text-xs bg-base-200 px-2 py-1 rounded">{{ $row->slip_barcode }}</code>
                        @endscope
                    </x-table>
                </div>
            @endif
        </x-card>
    </div>
</div>
