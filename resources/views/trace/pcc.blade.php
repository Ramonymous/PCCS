<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use App\Models\Customer\HPM\Pcc;
use App\Models\Customer\HPM\PccTrace;
use App\Models\Customer\HPM\PccEvent;

new class extends Component {
    use Toast;

    public string $search = '';
    public bool $showDetails = false;

    // Detail data
    public ?array $traceSummary = null;
    public array $events = [];
    public ?string $currentPccId = null; // Track current PCC for live updates
    public int $lastEventCount = 0; // Detect new events
    public bool $isCompleted = false; // True when status is DELIVERY (final stage)

    #[On('barcode-scanned')]
    public function handleScan(string $barcode): void
    {
        $this->search = $barcode;
        $this->find();
    }

    /**
     * Listen for external PCC updates from other components
     * Dispatch this event when scanning/updating PCC in other pages
     */
    #[On('pcc-trace-updated')]
    public function handleExternalUpdate(?string $pccId = null): void
    {
        // If specific PCC updated and matches current view, refresh immediately
        if ($pccId && $this->currentPccId === $pccId && $this->showDetails) {
            $this->refreshData();
        }
        // If no specific ID, refresh anyway if we're viewing details
        elseif (!$pccId && $this->showDetails) {
            $this->refreshData();
        }
    }

    public function find(): void
    {
        try {
            $q = trim($this->search);
            if ($q === '') {
                $this->warning(__('Enter barcode / slip number first.'), null, 'toast-top', 'o-exclamation-triangle', 'alert-warning', 10000);
                return;
            }

            $pcc = Pcc::with([
                'schedule:id,slip_number,schedule_date,adjusted_date,schedule_time,adjusted_time',
                'finishGood:id,alias,part_number,type'
            ])
                ->select('id','slip_no','slip_barcode','part_no','part_name','date','time')
                ->where('slip_barcode', $q)
                ->orWhere('slip_no', $q)
                ->first();

            if (!$pcc) {
                $this->error(__('Label not found in the system!'), null, 'toast-top');
                $this->dispatch('scan-feedback', type: 'error');
                return;
            }

            // Determine part type for workflow
            $isDirect = $pcc->finishGood && $pcc->finishGood->type === 'DIRECT';

            $trace = PccTrace::with('pcc:id,slip_no,part_no,part_name,slip_barcode')
                ->where('pcc_id', $pcc->id)
                ->first();

            $this->currentPccId = $pcc->id; // Store for live updates
            
            $currentStage = $trace?->event_type ?? 'BELUM DIPROSES'; // internal fallback (Indonesian) mapped to translation later
            
            // Calculate schedule info
            $scheduleDate = $pcc->schedule?->schedule_date;
            $adjustedDate = $pcc->schedule?->adjusted_date;
            $effectiveSchedule = $adjustedDate ?? $scheduleDate ?? $pcc->date;
            $scheduleTime = $pcc->schedule?->adjusted_time ?? $pcc->schedule?->schedule_time ?? $pcc->time;
            
            // Get actual delivery date from DELIVERY event
            $deliveryEvent = null;
            $deliveryDate = null;
            $isDelayed = false;
            $delayDays = 0;
            
            if ($trace && $currentStage === 'DELIVERY') {
                $deliveryEvent = PccEvent::where('pcc_trace_id', $trace->id)
                    ->where('event_type', 'DELIVERY')
                    ->orderBy('event_timestamp', 'desc')
                    ->first();
                    
                if ($deliveryEvent && $effectiveSchedule) {
                    $deliveryDate = $deliveryEvent->event_timestamp;
                    $scheduledDateTime = \Carbon\Carbon::parse($effectiveSchedule)->startOfDay();
                    $actualDateTime = \Carbon\Carbon::parse($deliveryDate)->startOfDay();
                    
                    // Compare dates only (ignore time for delay calculation)
                    if ($actualDateTime->gt($scheduledDateTime)) {
                        $isDelayed = true;
                        $delayDays = (int) $actualDateTime->diffInDays($scheduledDateTime);
                    }
                }
            }
            
            $this->traceSummary = [
                'slip_no' => $pcc->slip_no,
                'part_no' => $pcc->part_no,
                'part_name' => $pcc->part_name,
                'barcode' => $pcc->slip_barcode,
                'current_stage' => $currentStage,
                'current_timestamp' => $trace?->event_timestamp?->format('Y-m-d H:i:s') ?? null,
                // Part type
                'type' => $isDirect ? 'DIRECT' : 'ASSY',
                'is_direct' => $isDirect,
                // Schedule information
                'schedule_date' => $scheduleDate?->format('Y-m-d'),
                'adjusted_date' => $adjustedDate?->format('Y-m-d'),
                'effective_schedule' => $effectiveSchedule?->format('Y-m-d'),
                'schedule_time' => $scheduleTime ? \Carbon\Carbon::parse($scheduleTime)->format('H:i') : null,
                'delivery_date' => $deliveryDate?->format('Y-m-d H:i:s'),
                'is_delayed' => $isDelayed,
                'delay_days' => $delayDays,
                'was_rescheduled' => $adjustedDate !== null,
            ];

            $this->events = [];
            if ($trace) {
                $this->events = PccEvent::with('user:id,name')
                    ->where('pcc_trace_id', $trace->id)
                    ->orderBy('event_timestamp')
                    ->get()
                    ->map(fn($e) => [
                        'event_type' => $e->event_type,
                        'timestamp' => $e->event_timestamp->format('Y-m-d H:i:s'),
                        'user' => $e->user->name ?? 'Unknown',
                        'remarks' => $e->remarks,
                    ])
                    ->toArray();
            }

            $this->lastEventCount = count($this->events);
            $this->isCompleted = ($currentStage === 'DELIVERY'); // Stop polling if completed
            $this->showDetails = true;
            $this->dispatch('scan-feedback', type: 'success');
            
        } catch (\Exception $e) {
            \Log::error('Trace find error', [
                'user_id' => auth()->id(),
                'barcode' => $this->search,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error(__('A system error occurred. Please try again or contact the administrator.'), null, 'toast-top');
            $this->dispatch('scan-feedback', type: 'error');
        }
    }

    /**
     * Live update method - polls for new events when details are shown
     * Stops polling if status reaches DELIVERY (final stage)
     */
    public function refreshData(): void
    {
        // Skip if not showing details, no PCC tracked, or already completed
        if (!$this->showDetails || !$this->currentPccId || $this->isCompleted) {
            return;
        }

        try {
            $pcc = Pcc::with([
                'schedule:id,slip_number,schedule_date,adjusted_date,schedule_time,adjusted_time',
                'finishGood:id,alias,part_number,type'
            ])
                ->find($this->currentPccId);
            if (!$pcc) {
                return; // PCC was deleted
            }

            $trace = PccTrace::with('pcc:id,slip_no,part_no,part_name,slip_barcode')
                ->where('pcc_id', $pcc->id)
                ->first();

            // Update current stage and type
            $currentStage = $trace?->event_type ?? 'BELUM DIPROSES';
            $isDirect = $pcc->finishGood && $pcc->finishGood->type === 'DIRECT';
            
            $this->traceSummary['current_stage'] = $currentStage;
            $this->traceSummary['current_timestamp'] = $trace?->event_timestamp?->format('Y-m-d H:i:s') ?? null;
            $this->traceSummary['type'] = $isDirect ? 'DIRECT' : 'ASSY';
            $this->traceSummary['is_direct'] = $isDirect;

            // Update schedule info
            $scheduleDate = $pcc->schedule?->schedule_date;
            $adjustedDate = $pcc->schedule?->adjusted_date;
            $effectiveSchedule = $adjustedDate ?? $scheduleDate ?? $pcc->date;
            
            // Check for delivery and calculate delay
            if ($currentStage === 'DELIVERY' && $trace) {
                $deliveryEvent = PccEvent::where('pcc_trace_id', $trace->id)
                    ->where('event_type', 'DELIVERY')
                    ->orderBy('event_timestamp', 'desc')
                    ->first();
                    
                if ($deliveryEvent && $effectiveSchedule) {
                    $deliveryDate = $deliveryEvent->event_timestamp;
                    $scheduledDateTime = \Carbon\Carbon::parse($effectiveSchedule)->startOfDay();
                    $actualDateTime = \Carbon\Carbon::parse($deliveryDate)->startOfDay();
                    
                    $this->traceSummary['delivery_date'] = $deliveryDate->format('Y-m-d H:i:s');
                    
                    if ($actualDateTime->gt($scheduledDateTime)) {
                        $this->traceSummary['is_delayed'] = true;
                        $this->traceSummary['delay_days'] = (int) $actualDateTime->diffInDays($scheduledDateTime);
                    }
                }
                
                $this->isCompleted = true;
            }

            // Reload events
            $newEvents = [];
            if ($trace) {
                $newEvents = PccEvent::with('user:id,name')
                    ->where('pcc_trace_id', $trace->id)
                    ->orderBy('event_timestamp')
                    ->get()
                    ->map(fn($e) => [
                        'event_type' => $e->event_type,
                        'timestamp' => $e->event_timestamp->format('Y-m-d H:i:s'),
                        'user' => $e->user->name ?? 'Unknown',
                        'remarks' => $e->remarks,
                    ])
                    ->toArray();
            }

            // Notify user if new events detected
            if (count($newEvents) > $this->lastEventCount) {
                $newCount = count($newEvents) - $this->lastEventCount;
                $this->info(__('🔄 :count new events added!', ['count' => $newCount]), null, 'toast-top toast-end', 'o-information-circle', 'alert-info', 3000);
                $this->dispatch('scan-feedback', type: 'success');
                
                // If just reached final stage, notify user
                if ($this->isCompleted) {
                    $this->success(__('✅ Process completed - Status: DELIVERY'), null, 'toast-top toast-end', 'o-check-circle', 'alert-success', 5000);
                }
            }

            $this->events = $newEvents;
            $this->lastEventCount = count($newEvents);

        } catch (\Exception $e) {
            // Silent fail for polling errors
            \Log::error('Trace refresh error: ' . $e->getMessage());
        }
    }

    public function resetView(): void
    {
        $this->showDetails = false;
        $this->traceSummary = null;
        $this->events = [];
        $this->search = '';
        $this->currentPccId = null;
        $this->lastEventCount = 0;
        $this->isCompleted = false;
        
        // Ensure scanner is properly reset
        $this->dispatch('scanner-cleanup-trace-scanner');
    }

    public function with(): array
    {
        $headers = [
            ['key' => 'event_type', 'label' => 'Stage'],
            ['key' => 'timestamp', 'label' => 'Time'],
            ['key' => 'user', 'label' => 'User'],
            ['key' => 'remarks', 'label' => 'Remarks'],
        ];

        // Determine workflow stages based on part type
        $isDirect = $this->traceSummary['is_direct'] ?? false;
        $stages = $isDirect 
            ? ['PDI CHECK', 'RECEIVED', 'DELIVERY']  // DIRECT: Skip PRODUCTION CHECK
            : ['PRODUCTION CHECK', 'RECEIVED', 'PDI CHECK', 'DELIVERY'];  // ASSY: Full workflow
        
        $currentStage = $this->traceSummary['current_stage'] ?? null;
        $currentIndex = is_string($currentStage) ? array_search($currentStage, $stages) : -1;
        if ($currentIndex === false) { $currentIndex = -1; }

        return [
            'headers' => $headers,
            'events' => $this->events,
            'traceSummary' => $this->traceSummary,
            'showDetails' => $this->showDetails,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
        ];
    }
}; ?>

<div @if($showDetails && !$isCompleted) wire:poll.5s="refreshData" @endif>
    <x-header title="PCC Traceability" separator>
        <x-slot:middle class="!justify-end">
            @if($showDetails)
                <div class="flex items-center gap-2">
                    {{-- Status indicator - Live or Completed --}}
                    @if($isCompleted)
                        <div class="flex items-center gap-1.5 px-3 py-1.5 bg-info/10 rounded-full border border-info/20">
                            <x-icon name="o-check-circle" class="w-4 h-4 text-info" />
                            <span class="text-xs font-medium text-info">Completed</span>
                        </div>
                    @else
                        <div class="flex items-center gap-1.5 px-3 py-1.5 bg-success/10 rounded-full border border-success/20">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-success"></span>
                            </span>
                            <span class="text-xs font-medium text-success">Live</span>
                        </div>
                    @endif
                    
                    @if(!$isCompleted)
                        <x-button 
                            icon="o-arrow-path" 
                            class="btn-ghost btn-sm" 
                            wire:click="refreshData" 
                            spinner="refreshData" 
                            tooltip="Refresh data"
                            tooltip-position="left"
                        />
                    @endif
                    <x-button :label="__('Scan Again')" icon="o-qr-code" class="btn-outline btn-sm" wire:click="resetView" />
                </div>
            @endif
        </x-slot:middle>
    </x-header>

    {{-- TOP SECTION: Scanner/Search or Summary with Progress --}}
    <div class="mb-6">
        @if(!$showDetails)
            {{-- Scanner Mode --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <livewire:components.ui.qr-scanner 
                        scanner-id="trace-scanner"
                        :label="__('Scan Label')"
                        :placeholder="__('Scan barcode / type slip number...')"
                        :show-manual-input="false"
                        :stop-after-scan="true"
                        :cooldown-seconds="3"
                    />
                </div>
                <div>
                    <x-card :title="__('Manual Search')" shadow class="h-full flex flex-col justify-center">
                        <div class="flex gap-2">
                            <x-input wire:model.defer="search" :placeholder="__('Slip number or barcode')" class="flex-1" />
                            <x-button :label="__('Search')" icon="o-magnifying-glass" class="btn-primary" wire:click="find" spinner="find" />
                        </div>
                        
                        {{-- Loading Indicator --}}
                        <div wire:loading wire:target="find" class="mt-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <span class="loading loading-spinner loading-sm text-primary"></span>
                                <span class="text-sm text-base-content/70">{{ __('Searching data...') }}</span>
                            </div>
                        </div>
                        
                        <div wire:loading.remove wire:target="find" class="mt-4 text-center">
                            <p class="text-sm text-base-content/60">
                                <x-icon name="o-information-circle" class="w-4 h-4 inline" />
                                {{ __('Scan barcode or enter slip number for tracking') }}
                            </p>
                        </div>
                    </x-card>
                </div>
            </div>
        @else
            {{-- Summary Mode with animated icons --}}
            <x-card shadow class="bg-gradient-to-br from-primary/5 to-secondary/5">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Left: Label Info with animated icon --}}
                    <div class="lg:col-span-2">
                        <div class="flex items-start gap-4">
                            <div class="relative">
                                <div class="absolute inset-0 bg-primary/20 rounded-full blur-xl animate-pulse"></div>
                                <div class="relative bg-primary/10 p-4 rounded-full">
                                    <x-icon name="o-qr-code" class="w-10 h-10 text-primary" />
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-start gap-2 mb-2">
                                    <h3 class="text-xl font-bold flex-1">{{ $traceSummary['part_name'] }}</h3>
                                    @if(isset($traceSummary['type']))
                                        <x-badge 
                                            :value="$traceSummary['type']" 
                                            class="{{ $traceSummary['is_direct'] ? 'badge-info' : 'badge-primary' }} badge-sm" 
                                        />
                                    @endif
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-tag" class="w-4 h-4 text-base-content/60" />
                                        <span class="text-sm">Slip: <strong>{{ $traceSummary['barcode'] }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-cube" class="w-4 h-4 text-base-content/60" />
                                        <span class="text-sm">Part: <strong>{{ $traceSummary['part_no'] }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2 col-span-2">
                                        <x-icon name="o-clock" class="w-4 h-4 text-base-content/60" />
                                        <span class="text-sm">
                                            @if($traceSummary['current_timestamp'])
                                                {{ \Carbon\Carbon::parse($traceSummary['current_timestamp'])->format('d M Y, H:i') }}
                                            @else
                                                -
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Current Stage Badge --}}
                    <div class="flex items-center justify-center lg:justify-end">
                        <div class="text-center lg:text-right">
                            <p class="text-xs text-base-content/60 mb-1">{{ __('Current Status') }}</p>
                            @php
                                $badgeClass = match($traceSummary['current_stage']) {
                                    'BELUM DIPROSES' => 'badge-ghost',
                                    'DELIVERY' => 'badge-info',
                                    default => 'badge-success'
                                };
                            @endphp
                            <x-badge :value="$traceSummary['current_stage']" class="badge-lg {{ $badgeClass }}" />
                            @if($traceSummary['current_stage'] === 'DELIVERY')
                                <div class="flex items-center justify-center lg:justify-end gap-1 mt-1">
                                    <x-icon name="o-check-circle" class="w-3 h-3 text-info" />
                                    <span class="text-xs text-info">{{ __('Completed') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Progress Steps --}}
                <div class="mt-6">
                    {{-- Workflow Type Indicator --}}
                    @if(isset($traceSummary['type']))
                        <div class="flex items-center justify-center gap-2 mb-3">
                            <span class="text-xs text-base-content/60">Workflow:</span>
                            <span class="text-xs font-medium {{ $traceSummary['is_direct'] ? 'text-info' : 'text-primary' }}">
                                {{ $traceSummary['type'] }} Type
                                {{ $traceSummary['is_direct'] ? '(Direct Process)' : '(Assembly Process)' }}
                            </span>
                        </div>
                    @endif
                    
                    <ul class="steps steps-horizontal w-full">
                        @foreach($stages as $idx => $stage)
                            <li class="step {{ $currentIndex >= $idx ? 'step-primary' : '' }}">
                                <div class="flex flex-col items-center gap-1">
                                    @if($currentIndex >= $idx)
                                        <x-icon name="o-check-circle" class="w-5 h-5 text-primary" />
                                    @else
                                        <x-icon name="o-clock" class="w-5 h-5 text-base-content/30" />
                                    @endif
                                    <span class="text-xs hidden sm:inline">{{ $stage }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-card>

            {{-- Schedule Information Card --}}
            @if($traceSummary['effective_schedule'])
                <x-card shadow class="mt-6">
                    <x-slot:title>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-calendar-days" class="w-5 h-5 text-primary" />
                                <span>Delivery Schedule</span>
                            </div>
                            {{-- Color Legend --}}
                            <div class="hidden lg:flex items-center gap-4 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded bg-base-200 border border-base-300"></div>
                                    <span class="text-base-content/60">Scheduled</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded bg-warning/20 border border-warning/40"></div>
                                    <span class="text-base-content/60">Rescheduled</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded bg-success/20 border border-success/40"></div>
                                    <span class="text-base-content/60">On Time</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded bg-error/20 border border-error/40"></div>
                                    <span class="text-base-content/60">Delayed</span>
                                </div>
                            </div>
                        </div>
                    </x-slot:title>

                    {{-- Mobile Legend --}}
                    <div class="lg:hidden mb-4 p-3 bg-base-100 rounded-lg">
                        <div class="text-xs font-medium text-base-content/60 mb-2">Color Guide:</div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center gap-1.5">
                                <div class="w-3 h-3 rounded bg-base-200 border border-base-300"></div>
                                <span>Scheduled</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-3 h-3 rounded bg-warning/20 border border-warning/40"></div>
                                <span>Rescheduled</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-3 h-3 rounded bg-success/20 border border-success/40"></div>
                                <span>On Time</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-3 h-3 rounded bg-error/20 border border-error/40"></div>
                                <span>Delayed</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Planned Schedule --}}
                        <div class="p-4 bg-base-200 rounded-lg border-2 border-base-300">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-calendar" class="w-4 h-4 text-base-content/60" />
                                    <span class="text-xs text-base-content/60 font-medium">Original Schedule</span>
                                </div>
                                <x-icon name="o-information-circle" class="w-4 h-4 text-base-content/40" 
                                        title="Initial planned delivery date" />
                            </div>
                            <div class="font-semibold text-lg">
                                {{ \Carbon\Carbon::parse($traceSummary['schedule_date'] ?? $traceSummary['effective_schedule'])->format('d M Y') }}
                            </div>
                            @if($traceSummary['schedule_time'])
                                <div class="text-sm text-base-content/70 mt-1">
                                    <x-icon name="o-clock" class="w-3 h-3 inline" />
                                    {{ $traceSummary['schedule_time'] }}
                                </div>
                            @endif
                            @if($traceSummary['was_rescheduled'])
                                <div class="mt-2">
                                    <span class="badge badge-warning badge-sm">Rescheduled</span>
                                </div>
                            @endif
                        </div>

                        {{-- Adjusted Schedule (if exists) --}}
                        @if($traceSummary['adjusted_date'])
                            <div class="p-4 bg-warning/10 border-2 border-warning/40 rounded-lg">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-arrow-path" class="w-4 h-4 text-warning" />
                                        <span class="text-xs text-warning font-medium">Rescheduled Date</span>
                                    </div>
                                    <x-icon name="o-information-circle" class="w-4 h-4 text-warning/60" 
                                            title="New delivery date after rescheduling" />
                                </div>
                                <div class="font-semibold text-lg text-warning">
                                    {{ \Carbon\Carbon::parse($traceSummary['adjusted_date'])->format('d M Y') }}
                                </div>
                                @if($traceSummary['schedule_time'])
                                    <div class="text-sm text-base-content/70 mt-1">
                                        <x-icon name="o-clock" class="w-3 h-3 inline" />
                                        {{ $traceSummary['schedule_time'] }}
                                    </div>
                                @endif
                                @php
                                    $originalDate = \Carbon\Carbon::parse($traceSummary['schedule_date'])->startOfDay();
                                    $newDate = \Carbon\Carbon::parse($traceSummary['adjusted_date'])->startOfDay();
                                    $rescheduleDiff = (int) $newDate->diffInDays($originalDate);
                                    $isPostponed = $newDate->gt($originalDate);
                                @endphp
                                <div class="text-xs text-base-content/60 mt-1">
                                    {{ $isPostponed ? '+' : '-' }}{{ $rescheduleDiff }} day{{ $rescheduleDiff !== 1 ? 's' : '' }} from original
                                </div>
                            </div>
                        @endif

                        {{-- Actual Delivery --}}
                        <div class="p-4 rounded-lg border-2 {{ $traceSummary['delivery_date'] ? ($traceSummary['is_delayed'] ? 'bg-error/10 border-error/40' : 'bg-success/10 border-success/40') : 'bg-base-200 border-base-300' }}">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-truck" class="w-4 h-4 {{ $traceSummary['delivery_date'] ? ($traceSummary['is_delayed'] ? 'text-error' : 'text-success') : 'text-base-content/60' }}" />
                                    <span class="text-xs font-medium {{ $traceSummary['delivery_date'] ? ($traceSummary['is_delayed'] ? 'text-error' : 'text-success') : 'text-base-content/60' }}">
                                        Actual Delivery
                                    </span>
                                </div>
                                <x-icon name="o-information-circle" 
                                        class="w-4 h-4 {{ $traceSummary['delivery_date'] ? ($traceSummary['is_delayed'] ? 'text-error/60' : 'text-success/60') : 'text-base-content/40' }}" 
                                        title="When the item was actually delivered" />
                            </div>
                            @if($traceSummary['delivery_date'])
                                <div class="font-semibold text-lg {{ $traceSummary['is_delayed'] ? 'text-error' : 'text-success' }}">
                                    {{ \Carbon\Carbon::parse($traceSummary['delivery_date'])->format('d M Y') }}
                                </div>
                                <div class="text-sm text-base-content/70 mt-1">
                                    <x-icon name="o-clock" class="w-3 h-3 inline" />
                                    {{ \Carbon\Carbon::parse($traceSummary['delivery_date'])->format('H:i') }}
                                </div>
                                @if($traceSummary['is_delayed'])
                                    <div class="mt-2 flex items-center gap-1">
                                        <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-error" />
                                        <span class="text-sm text-error font-medium">
                                            Delay: {{ $traceSummary['delay_days'] }} day{{ $traceSummary['delay_days'] > 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                @else
                                    <div class="mt-2">
                                        <span class="badge badge-success badge-sm">On Time</span>
                                    </div>
                                @endif
                            @else
                                <div class="text-base-content/50 italic">Not delivered yet</div>
                            @endif
                        </div>
                    </div>

                    {{-- Summary Alert --}}
                    @if($traceSummary['is_delayed'])
                        <x-alert title="Delivery Status" icon="o-exclamation-triangle" class="alert-error mt-4">
                            Delivered <strong>{{ $traceSummary['delay_days'] }} day{{ $traceSummary['delay_days'] > 1 ? 's' : '' }} late</strong> 
                            from the {{ $traceSummary['was_rescheduled'] ? 'adjusted' : 'scheduled' }} date.
                        </x-alert>
                    @elseif($traceSummary['delivery_date'])
                        <x-alert title="Delivery Status" icon="o-check-circle" class="alert-success mt-4">
                            Delivered on time as {{ $traceSummary['was_rescheduled'] ? 'adjusted schedule' : 'planned' }}.
                        </x-alert>
                    @elseif($traceSummary['was_rescheduled'])
                        <x-alert title="Schedule Updated" icon="o-information-circle" class="alert-warning mt-4">
                            Delivery date has been rescheduled from 
                            <strong>{{ \Carbon\Carbon::parse($traceSummary['schedule_date'])->format('d M Y') }}</strong> to 
                            <strong>{{ \Carbon\Carbon::parse($traceSummary['adjusted_date'])->format('d M Y') }}</strong>.
                        </x-alert>
                    @endif
                </x-card>
            @endif
        @endif
    </div>

    {{-- BOTTOM SECTION: Timeline --}}
    @if($showDetails)
        <x-card shadow>
            <x-slot:title>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span>History Timeline</span>
                        @if(!empty($events))
                            <span class="badge badge-neutral badge-sm">{{ count($events) }} events</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if($isCompleted)
                            <div class="flex items-center gap-2 text-sm text-info">
                                <x-icon name="o-check-badge" class="w-4 h-4" />
                                <span class="text-xs font-medium">Process completed</span>
                            </div>
                        @else
                            <div wire:loading wire:target="refreshData" class="flex items-center gap-2 text-sm text-base-content/60">
                                <span class="loading loading-spinner loading-xs"></span>
                                <span class="hidden sm:inline">Checking updates...</span>
                            </div>
                            <div wire:loading.remove wire:target="refreshData" class="text-xs text-base-content/50">
                                Auto-refresh every 5s
                            </div>
                        @endif
                    </div>
                </div>
            </x-slot:title>
            
            @if(empty($events))
                <div class="text-center py-16">
                    <div class="relative inline-block">
                        <div class="absolute inset-0 bg-warning/20 rounded-full blur-2xl animate-pulse"></div>
                        <x-icon name="o-exclamation-triangle" class="relative w-16 h-16 mx-auto mb-4 text-warning opacity-50" />
                    </div>
                    <p class="text-base-content/60">{{ __('No history for this label yet.') }}</p>
                </div>
            @else
                <div class="relative pl-8 pr-4">
                    {{-- Vertical timeline line --}}
                    <div class="absolute left-3 top-0 bottom-0 w-1 bg-gradient-to-b from-primary via-primary/50 to-primary/20 rounded-full"></div>

                    @foreach($events as $i => $e)
                        <div x-data="{ show:false }" x-init="setTimeout(()=>show=true, {{ $i * 100 }})" class="relative mb-6 last:mb-0">
                            {{-- Timeline node with icon --}}
                            <div class="absolute -left-[11px] top-2 w-7 h-7 rounded-full flex items-center justify-center
                                        {{ $i === array_key_last($events) ? 'bg-primary ring-4 ring-primary/20' : 'bg-base-300 ring-4 ring-base-100' }}
                                        transition-all duration-300">
                                @if($i === array_key_last($events))
                                    <x-icon name="o-check" class="w-4 h-4 text-primary-content" />
                                @else
                                    <div class="w-2 h-2 rounded-full bg-base-content/40"></div>
                                @endif
                            </div>

                            {{-- Event Card --}}
                            <div :class="show ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'" 
                                 class="transition-all duration-500 ease-out ml-4">
                                <div class="group p-4 rounded-xl bg-base-200 hover:bg-base-300 hover:shadow-lg transition-all duration-200">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1">
                                            {{-- Stage with icon --}}
                                            <div class="flex items-center gap-2 mb-2">
                                                @php
                                                    $stageIcons = [
                                                        'PRODUCTION CHECK' => 'o-wrench-screwdriver',
                                                        'RECEIVED' => 'o-inbox-arrow-down',
                                                        'PDI CHECK' => 'o-clipboard-document-check',
                                                        'DELIVERY' => 'o-truck'
                                                    ];
                                                    $icon = $stageIcons[$e['event_type']] ?? 'o-check-circle';
                                                @endphp
                                                <x-icon :name="$icon" class="w-5 h-5 text-primary" />
                                                <h4 class="font-semibold text-base">{{ $e['event_type'] }}</h4>
                                            </div>

                                            {{-- User and remarks --}}
                                            <div class="flex items-center gap-2 text-sm text-base-content/70">
                                                <x-icon name="o-user" class="w-4 h-4" />
                                                <span>{{ $e['user'] }}</span>
                                            </div>
                                            @if(!empty($e['remarks']))
                                                <div class="mt-2 p-2 bg-base-100 rounded text-sm text-base-content/80 italic">
                                                    <x-icon name="o-chat-bubble-left-ellipsis" class="w-4 h-4 inline" />
                                                    {{ $e['remarks'] }}
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Timestamp --}}
                                        <div class="text-right whitespace-nowrap">
                                            <div class="text-sm font-medium text-primary">
                                                {{ \Carbon\Carbon::parse($e['timestamp'])->diffForHumans() }}
                                            </div>
                                            <div class="text-xs text-base-content/50 mt-1">
                                                {{ \Carbon\Carbon::parse($e['timestamp'])->format('d M Y') }}
                                            </div>
                                            <div class="text-xs text-base-content/40">
                                                {{ \Carbon\Carbon::parse($e['timestamp'])->format('H:i') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif

    {{-- Audio Feedback --}}
    <x-scanner.audio-feedback />
</div>
