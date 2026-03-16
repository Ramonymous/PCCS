<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use App\Traits\Livewire\HasScannerLock;
use App\Traits\Livewire\ProcessesScan;
use App\Services\PccTraceService;
use App\Models\Customer\HPM\Pcc;
use App\Models\Customer\HPM\PccTrace;
use App\Models\Customer\HPM\PccEvent;
use App\Models\Master\CCP;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use Toast, HasScannerLock, ProcessesScan;

    public array $recentScans = [];
    public string $eventType = 'DELIVERY';
    public string $remarks = '';
    
    // Modal states
    public bool $showCcpModal = false;
    public bool $showConfirmModal = false;
    public ?Pcc $pendingPcc = null;
    public ?PccTrace $pendingTrace = null;
    public array $ccpItems = [];
    public string $effectiveDateInfo = '';
    public string $currentDateInfo = '';
    
    // Lightbox state
    public bool $showLightbox = false;
    public ?string $lightboxImage = null;
    public ?int $lightboxIndex = null;

    // Scanner identifier for global locking
    private const SCANNER_ID = 'delivery-scanner';

    // Override unlock permission
    protected function getUnlockPermission(): string
    {
        return 'delivery.unlock-scanner';
    }

    public function mount(): void
    {
        $this->loadRecentScans();
    }

    public function loadRecentScans(): void
    {
        $this->recentScans = PccTrace::with(['pcc:id,slip_no,part_no,part_name,slip_barcode'])
            ->where('event_type', $this->eventType)
            ->latest('event_timestamp')
            ->limit(20)
            ->get()
            ->map(fn($trace) => [
                'id' => $trace->id,
                'slip_no' => $trace->pcc->slip_no ?? 'N/A',
                'part_no' => $trace->pcc->part_no ?? 'N/A',
                'part_name' => $trace->pcc->part_name ?? 'N/A',
                'barcode' => $trace->pcc->slip_barcode ?? 'N/A',
                'timestamp' => $trace->event_timestamp->format('Y-m-d H:i:s'),
                'remarks' => $trace->remarks,
            ])
            ->toArray();
    }

    #[On('barcode-scanned')]
    public function processScan(string $barcode): void
    {
        try {
            // Check global lock state first
            if ($this->checkAndCleanupLock()) {
                return;
            }

            // Validate PCC and load CCPs for delivery stage
            $with = [
                'schedule:id,slip_number,schedule_date,adjusted_date,schedule_time,adjusted_time',
                'finishGood:id,alias,part_number,type',
                'finishGood.ccps' => function ($q) {
                    $q->where('is_active', true)
                        ->forStage('DELIVERY')
                        ->select('id', 'finish_good_id', 'stage', 'check_point_img', 'revision', 'description', 'is_active');
                }
            ];
            $result = PccTraceService::findByBarcodeWithTrace($barcode, $with);
            $pcc = $result['pcc'];
            $trace = $result['trace'];

            if (!$pcc) {
                $this->error(__('Label not found in the system!'), null, 'toast-top');
                $this->dispatch('scan-feedback', type: 'error');
                return;
            }

            // // Must have gone through some previous stage first (trace exists)
            // if (!$trace) {
            //     $this->warning(__('Label has not been processed. Must go through previous stage first.'), null, 'toast-top', 'o-exclamation-triangle', 'alert-warning', 10000);
            //     $this->dispatch('scan-feedback', type: 'warning');
            //     return;
            // }
            
            // Special handling for already delivered (must check before validateStageAndCheckDuplicates)
            if ($trace->event_type === 'DELIVERY') {
                $lastEvent = PccTraceService::getLastEvent($trace, 'DELIVERY');
                $deliveryTime = $lastEvent ? $lastEvent->event_timestamp->format('d M Y H:i') : 'N/A';
                $this->warning(__('Label already delivered! This label was scanned for DELIVERY on :time. Cannot scan again!', ['time' => $deliveryTime]), null, 'toast-top', 'o-exclamation-triangle', 'alert-warning', 10000);
                $this->dispatch('scan-feedback', type: 'error');

                Log::warning('Delivery - Already delivered attempt', [
                    'user_id' => Auth::id(),
                    'pcc_id' => $pcc->id,
                    'current_stage' => $trace->event_type,
                    'delivery_timestamp' => $lastEvent?->event_timestamp,
                    'slip_no' => $pcc->slip_no,
                ]);
                $this->lockScanner(0, 'already-delivered', [
                    'slip_no' => $pcc->slip_no,
                    'delivery_time' => $deliveryTime,
                ]);
                return;
            }

            // Validate stage transition and check duplicates
            $isDirect = PccTraceService::isDirect($pcc);
            $trace = $this->validateStageAndCheckDuplicates($pcc, $trace, $this->eventType, $isDirect);
            if (!$trace) return;

            // Store PCC and trace for later use
            $this->pendingPcc = $pcc;
            $this->pendingTrace = $trace;

            // Load CCPs for confirmation
            $this->loadCcps($pcc);

            // Show CCP confirmation modal
            $this->showCcpModal = true;
            $this->dispatch('scan-feedback', type: 'success');

        } catch (\Exception $e) {
            $this->logScanError('Delivery', $barcode, $e);
            $this->showGenericError();
        }
    }

    // Load CCPs for the scanned PCC
    protected function loadCcps(Pcc $pcc): void
    {
        $activeCcps = collect();
        if ($pcc->relationLoaded('finishGood') && $pcc->finishGood) {
            $activeCcps = ($pcc->finishGood->ccps ?? collect())
                ->filter(fn($c) => (bool) $c->is_active)
                ->values();
        }

        if ($activeCcps && $activeCcps->count() > 0) {
            $this->ccpItems = $activeCcps->map(function ($ccp) {
                return [
                    'id' => $ccp->id,
                    'img' => $ccp->check_point_img ? \Storage::url('hpm/ccp/' . $ccp->check_point_img) : null,
                    'revision' => $ccp->revision,
                    'description' => $ccp->description,
                ];
            })->toArray();
        } else {
            $this->ccpItems = [];
        }
    }

    // User confirms CCP - proceed to check schedule date
    public function confirmSubmit(): void
    {
        if (!$this->pendingPcc || !$this->pendingTrace) {
            $this->error(__('Invalid data.'), null, 'toast-top');
            return;
        }

        // Close CCP modal
        $this->showCcpModal = false;

        // Check effective date (from schedule via accessor)
        $effectiveDate = $this->pendingPcc->effective_date instanceof \Carbon\CarbonInterface
            ? $this->pendingPcc->effective_date->toDateString()
            : (string) $this->pendingPcc->effective_date;
        
        $currentDate = now()->toDateString();

        // If delivery is not on the effective date, show date confirmation modal
        if ($effectiveDate !== $currentDate) {
            $this->effectiveDateInfo = \Carbon\Carbon::parse($effectiveDate)->format('d M Y');
            $this->currentDateInfo = now()->format('d M Y');
            $this->showConfirmModal = true;
            $this->dispatch('scan-feedback', type: 'warning');
            return;
        }

        // If on schedule, proceed directly
        $this->confirmDelivery();
    }

    public function confirmDelivery(): void
    {
        if (!$this->pendingPcc || !$this->pendingTrace) {
            $this->error(__('Invalid data.'), null, 'toast-top');
            return;
        }

        try {
            DB::beginTransaction();

            // Refresh PCC to get latest data including ship quantity
            $pcc = Pcc::with('finishGood:id,alias,part_number,stock')
                ->find($this->pendingPcc->id);
            
            if (!$pcc) {
                $this->error(__('PCC not found.'), null, 'toast-top');
                DB::rollBack();
                return;
            }

            // Check stock availability before delivery
            if ($pcc->finishGood && $pcc->ship) {
                if ($pcc->finishGood->stock < $pcc->ship) {
                    $this->error(__('Insufficient stock! Available: :available, Required: :required', ['available' => $pcc->finishGood->stock, 'required' => $pcc->ship]), null, 'toast-top');
                    $this->dispatch('scan-feedback', type: 'error');
                    DB::rollBack();
                    return;
                }
            }

            // Update PccTrace to new stage (current label state)
            $this->pendingTrace->update([
                'event_type' => $this->eventType,
                'event_timestamp' => now(),
                'remarks' => $this->remarks ?: null,
            ]);

            // Log to PccEvent (historical log)
            PccEvent::create([
                'pcc_trace_id' => $this->pendingTrace->id,
                'event_users' => Auth::id(),
                'event_type' => $this->pendingTrace->event_type,
                'event_timestamp' => $this->pendingTrace->event_timestamp,
                'remarks' => $this->pendingTrace->remarks,
            ]);

            // Subtract stock from finish good (DELIVERY = outgoing stock)
            if ($pcc->finishGood && $pcc->ship) {
                $pcc->finishGood->decrement('stock', $pcc->ship);
                
                Log::info('Stock deducted on DELIVERY', [
                    'pcc_id' => $pcc->id,
                    'slip_no' => $pcc->slip_no,
                    'part_number' => $pcc->finishGood->part_number,
                    'quantity_delivered' => $pcc->ship,
                    'new_stock' => $pcc->finishGood->fresh()->stock,
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();

            $partNumber = $pcc->finishGood->part_number ?? $pcc->part_no;
            $stockInfo = $pcc->ship ? " (-{$pcc->ship} stock)" : '';
            $this->success("✓ {$partNumber} - {$pcc->slip_no}{$stockInfo}", null, 'toast-top');
            $this->dispatch('scan-feedback', type: 'success');
            
            // Notify trace page for live updates
            $this->dispatch('pcc-trace-updated', pccId: $pcc->id);
            
            // Reload recent scans
            $this->loadRecentScans();

            // Reset all state
            $this->cancelDelivery();

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detailed error for debugging
            Log::error('Delivery - Confirmation failed', [
                'user_id' => Auth::id(),
                'pcc_id' => $this->pendingPcc->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error(__('A system error occurred while saving data. Please try again.'), null, 'toast-top');
            $this->dispatch('scan-feedback', type: 'error');
        }
    }

    public function openLightbox(int $index): void
    {
        if (isset($this->ccpItems[$index]['img'])) {
            $this->lightboxImage = $this->ccpItems[$index]['img'];
            $this->lightboxIndex = $index;
            $this->showLightbox = true;
        }
    }

    public function closeLightbox(): void
    {
        $this->showLightbox = false;
        $this->lightboxImage = null;
        $this->lightboxIndex = null;
    }

    public function nextImage(): void
    {
        if ($this->lightboxIndex !== null && isset($this->ccpItems[$this->lightboxIndex + 1]['img'])) {
            $this->lightboxIndex++;
            $this->lightboxImage = $this->ccpItems[$this->lightboxIndex]['img'];
        }
    }

    public function previousImage(): void
    {
        if ($this->lightboxIndex !== null && $this->lightboxIndex > 0 && isset($this->ccpItems[$this->lightboxIndex - 1]['img'])) {
            $this->lightboxIndex--;
            $this->lightboxImage = $this->ccpItems[$this->lightboxIndex]['img'];
        }
    }

    public function cancelCcp(): void
    {
        $this->showCcpModal = false;
        $this->pendingPcc = null;
        $this->pendingTrace = null;
        $this->ccpItems = [];
        $this->closeLightbox();
    }

    public function cancelDelivery(): void
    {
        $this->showConfirmModal = false;
        $this->showCcpModal = false;
        $this->pendingPcc = null;
        $this->pendingTrace = null;
        $this->ccpItems = [];
        $this->effectiveDateInfo = '';
        $this->currentDateInfo = '';
    }

    public function clearRemarks(): void
    {
        $this->remarks = '';
    }

    public function with(): array { return []; }
}; ?>

<div>
    <x-header :title="__('HPM Delivery')" :subtitle="__('Scanner Delivery HPM')" separator>
        <x-slot:middle class="!justify-end">
            <x-button :label="__('Refresh')" icon="o-arrow-path" class="btn-sm" wire:click="loadRecentScans" />
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Scanner Section --}}
        <div class="space-y-4">
            {{-- QR Scanner Component --}}
            <livewire:components.ui.qr-scanner 
                scanner-id="delivery-scanner"
                :label="__('Scanner')"
                :placeholder="__('Scan atau ketik barcode/slip number...')"
                :show-manual-input="true"
                :cooldown-seconds="3"
            />
        </div>

        {{-- Recent Scans Section --}}
        <div>
            <x-card :title="__('Recent Scans') . ' (' . count($recentScans) . ')'" shadow>
                <div class="space-y-2 max-h-[700px] overflow-y-auto">
                    @forelse($recentScans as $scan)
                        <div class="p-3 bg-base-200 rounded-lg hover:bg-base-300 transition">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="font-semibold text-sm">{{ $scan['slip_no'] }}</div>
                                    <div class="text-xs text-gray-600">{{ $scan['part_no'] }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ $scan['part_name'] }}</div>
                                    @if($scan['remarks'])
                                        <div class="text-xs text-blue-600 italic mt-1">{{ $scan['remarks'] }}</div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($scan['timestamp'])->diffForHumans() }}</div>
                                    <x-badge value="✓" class="badge-success badge-sm mt-1" />
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <x-icon name="o-qr-code" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                            <p>{{ __('No scans yet') }}</p>
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>

    {{-- CCP Full-Screen Confirmation - Mobile Optimized for Single Image --}}
    @if($showCcpModal && $pendingPcc)
        <div class="fixed inset-0 z-50 bg-black"
            x-data="{ 
                zoom: 1,
                rotation: 0,
                panX: 0,
                panY: 0,
                isDragging: false,
                startX: 0,
                startY: 0,
                isPinching: false,
                initialDistance: 0,
                
                // Pinch zoom handlers
                handleTouchStart(e) {
                    if (e.touches.length === 2) {
                        this.isPinching = true;
                        this.initialDistance = Math.hypot(
                            e.touches[0].clientX - e.touches[1].clientX,
                            e.touches[0].clientY - e.touches[1].clientY
                        );
                    } else if (e.touches.length === 1 && this.zoom > 1) {
                        this.isDragging = true;
                        this.startX = e.touches[0].clientX - this.panX;
                        this.startY = e.touches[0].clientY - this.panY;
                    }
                },
                
                handleTouchMove(e) {
                    if (this.isPinching && e.touches.length === 2) {
                        e.preventDefault();
                        const currentDistance = Math.hypot(
                            e.touches[0].clientX - e.touches[1].clientX,
                            e.touches[0].clientY - e.touches[1].clientY
                        );
                        const scale = currentDistance / this.initialDistance;
                        this.zoom = Math.min(Math.max(1, this.zoom * scale), 5);
                        this.initialDistance = currentDistance;
                    } else if (this.isDragging && e.touches.length === 1) {
                        e.preventDefault();
                        this.panX = e.touches[0].clientX - this.startX;
                        this.panY = e.touches[0].clientY - this.startY;
                    }
                },
                
                handleTouchEnd() {
                    this.isPinching = false;
                    this.isDragging = false;
                },
                
                resetView() {
                    this.zoom = 1;
                    this.panX = 0;
                    this.panY = 0;
                },
                
                rotateImage() {
                    this.rotation = (this.rotation + 90) % 360;
                },
                
                zoomIn() {
                    this.zoom = Math.min(this.zoom + 0.5, 5);
                },
                
                zoomOut() {
                    if (this.zoom > 1) {
                        this.zoom = Math.max(this.zoom - 0.5, 1);
                        if (this.zoom === 1) {
                            this.panX = 0;
                            this.panY = 0;
                        }
                    }
                }
            }">
            
            {{-- Top Bar with Part Info --}}
            <div class="absolute top-0 left-0 right-0 bg-gradient-to-b from-black/95 to-transparent p-3 z-20">
                <div class="flex items-center justify-between">
                    <div class="text-white flex-1 min-w-0">
                        <div class="text-sm md:text-base font-bold truncate">
                            {{ $pendingPcc->finishGood->part_number ?? $pendingPcc->part_no }}
                        </div>
                        <div class="text-xs opacity-75 truncate">
                            {{ __('Slip No') }}: {{ $pendingPcc->slip_no }}
                        </div>
                    </div>
                    
                    {{-- Zoom Controls (Desktop) --}}
                    <div class="hidden md:flex items-center gap-2 ml-4">
                        <x-button @click="zoomOut" icon="o-minus" class="btn-sm btn-circle btn-ghost text-white" x-show="zoom > 1" />
                        <div class="text-white text-sm font-mono" x-show="zoom > 1">
                            <span x-text="Math.round(zoom * 100)"></span>%
                        </div>
                        <x-button @click="zoomIn" icon="o-plus" class="btn-sm btn-circle btn-ghost text-white" />
                        <x-button @click="rotateImage" icon="o-arrow-path" class="btn-sm btn-circle btn-ghost text-white" />
                        <x-button @click="resetView" icon="o-arrow-uturn-left" class="btn-sm btn-circle btn-ghost text-white" x-show="zoom > 1 || rotation !== 0" />
                    </div>
                </div>
            </div>

            {{-- Mobile Tool Buttons --}}
            <div class="md:hidden absolute top-16 right-3 z-20 flex flex-col gap-2">
                <x-button @click="rotateImage" icon="o-arrow-path" class="btn-sm btn-circle btn-ghost text-white bg-black/50" />
                <x-button @click="resetView" icon="o-arrow-uturn-left" class="btn-sm btn-circle btn-ghost text-white bg-black/50" x-show="zoom > 1 || rotation !== 0" />
            </div>

            {{-- Warning if no CCP --}}
            @if(count($ccpItems) === 0 || empty($ccpItems[0]['img']))
                <div class="absolute top-20 left-4 right-4 z-10">
                    <div class="alert alert-warning">
                        <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
                        <span class="text-sm">{{ __('No CCP for this part') }}</span>
                    </div>
                </div>
            @endif

            {{-- Main Image Container with Pinch Zoom --}}
            <div class="flex items-center justify-center h-full w-full overflow-hidden"
                @touchstart="handleTouchStart($event)"
                @touchmove="handleTouchMove($event)"
                @touchend="handleTouchEnd()">
                
                @if (count($ccpItems) > 0 && $ccpItems[0]['img'])
                    <div class="relative w-full h-full flex items-center justify-center p-4 pt-20 pb-36">
                        <img src="{{ $ccpItems[0]['img'] }}" 
                            alt="CCP Image" 
                            class="max-w-full max-h-full object-contain transition-transform duration-100 touch-none select-none"
                            :style="`transform: scale(${zoom}) rotate(${rotation}deg) translate(${panX / zoom}px, ${panY / zoom}px);`"
                            style="transform-origin: center center;" />
                    </div>
                    
                    {{-- Zoom Indicator (Mobile) --}}
                    <div class="md:hidden absolute top-20 left-4 z-10" x-show="zoom > 1" x-transition>
                        <div class="badge badge-primary badge-lg font-mono font-bold">
                            <span x-text="Math.round(zoom * 100)"></span>%
                        </div>
                    </div>
                    
                    {{-- Instructions Overlay (shows briefly) --}}
                    <div class="md:hidden absolute inset-0 flex items-center justify-center pointer-events-none z-10"
                        x-data="{ show: true }"
                        x-init="setTimeout(() => show = false, 3000)"
                        x-show="show"
                        x-transition.opacity.duration.500ms>
                        <div class="bg-black/80 backdrop-blur-sm rounded-2xl p-6 text-center text-white max-w-xs mx-4">
                            <x-icon name="o-hand-raised" class="w-12 h-12 mx-auto mb-3 animate-pulse" />
                            <p class="font-semibold mb-2">{{ __('Check CCP carefully') }}</p>
                            <p class="text-sm opacity-90">{{ __('Pinch to zoom') }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('Swipe to pan') }}</p>
                        </div>
                    </div>
                    
                @else
                    {{-- No CCP Image --}}
                    <div class="text-center text-white p-8">
                        <x-icon name="o-exclamation-triangle" class="w-20 h-20 mx-auto mb-4 opacity-50" />
                        <p class="text-lg font-semibold">{{ __('No CCP image') }}</p>
                        <p class="text-sm opacity-75 mt-2">{{ __('Continue to proceed with delivery') }}</p>
                    </div>
                @endif
            </div>

            {{-- Action Buttons --}}
            <div class="absolute bottom-4 md:bottom-6 left-0 right-0 px-4 z-20">
                <div class="max-w-md mx-auto flex gap-3">
                    <x-button 
                        :label="__('Cancel')" 
                        icon="o-x-mark" 
                        class="flex-1 btn-lg btn-error" 
                        wire:click="cancelCcp" 
                        spinner />
                    
                    <x-button 
                        :label="__('Confirm ✓')" 
                        icon="o-check" 
                        class="flex-1 btn-lg btn-success" 
                        wire:click="confirmSubmit" 
                        spinner />
                </div>
            </div>

            {{-- Help Text --}}
            <div class="absolute bottom-20 md:bottom-24 left-0 right-0 text-center z-10">
                <p class="text-white/60 text-xs">
                    <span class="md:hidden">{{ __('Pinch to zoom') }} • {{ __('Swipe to pan') }}</span>
                    <span class="hidden md:inline">{{ __('Use zoom buttons or mouse scroll') }}</span>
                </p>
            </div>
        </div>
    @endif

    {{-- Confirmation Modal for Off-Schedule Delivery --}}
    <x-modal wire:model="showConfirmModal" :title="__('Konfirmasi Pengiriman')" separator persistent>
        <div class="space-y-4">
            <div class="alert alert-warning">
                <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                <div>
                    <h3 class="font-bold">{{ __('Off-Schedule Delivery!') }}</h3>
                    <div class="text-sm">{{ __('This label is scheduled for a different date.') }}</div>
                </div>
            </div>

            @if($pendingPcc)
                <div class="bg-base-200 p-4 rounded-lg space-y-2">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="font-semibold">{{ __('Part Number') }}:</div>
                        <div>{{ $pendingPcc->part_no }}</div>
                        
                        <div class="font-semibold">{{ __('Part Name') }}:</div>
                        <div>{{ $pendingPcc->part_name }}</div>
                        
                        <div class="font-semibold">{{ __('Slip No') }}:</div>
                        <div>{{ $pendingPcc->slip_no }}</div>
                        
                        <div class="font-semibold text-error">{{ __('Schedule Date:') }}</div>
                        <div class="text-error font-bold">{{ $effectiveDateInfo }}</div>
                        
                        <div class="font-semibold text-success">{{ __('Current Date:') }}</div>
                        <div class="text-success font-bold">{{ $currentDateInfo }}</div>
                    </div>
                </div>
            @endif

            <div class="text-sm text-gray-600">
                <p class="mb-2"><strong>{{ __('Notes:') }}</strong></p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ __('This delivery will be recorded with date') }} <strong>{{ $currentDateInfo }}</strong></li>
                    <li>{{ __('Original schedule is') }} <strong>{{ $effectiveDateInfo }}</strong></li>
                    <li>{{ __('Make sure there is supervisor confirmation to continue') }}</li>
                </ul>
            </div>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="cancelDelivery" />
            <x-button :label="__('Yes, Continue Delivery')" class="btn-warning" wire:click="confirmDelivery" />
        </x-slot:actions>
    </x-modal>

    {{-- Scanner Lock Overlay --}}
    <x-scanner.lock-overlay 
        :show="$this->scannerLocked"
        :lockRemainingSeconds="$this->lockRemainingSeconds"
        :canUnlock="auth()->user() && auth()->user()->can('delivery.unlock-scanner')"
        :title="$this->activeLock && $this->activeLock->reason === 'already-delivered' ? __('Label Already Delivered') : ($this->activeLock && $this->activeLock->reason === 'duplicate-scan' ? __('⚠️ Duplicate Detected') : __('Scanner Locked'))"
        :subtitle="$this->activeLock && $this->activeLock->reason === 'already-delivered' ? __('This label has already gone through the DELIVERY process.') : ($this->activeLock && $this->activeLock->reason === 'duplicate-scan' ? __('Label was just scanned.') : __('Scanner temporarily inactive.'))"
        :alertMessage="$this->activeLock && $this->activeLock->reason === 'already-delivered' ? __('Label with Slip No: :slip was already delivered on :time. Cannot be scanned again for DELIVERY!', ['slip' => $this->activeLock->metadata['slip_no'] ?? 'N/A', 'time' => $this->activeLock->metadata['delivery_time'] ?? 'N/A']) : ($this->activeLock && $this->activeLock->reason === 'duplicate-scan' ? __('Label with Slip No: :slip was just scanned on :time. Wait a moment before scanning again.', ['slip' => $this->activeLock->metadata['slip_no'] ?? 'N/A', 'time' => $this->activeLock->metadata['recent_time'] ?? 'N/A']) : __('Scanner locked for system security. Contact supervisor if unlock is needed.'))"
        :footerMessage="__('Please wait until time ends or contact supervisor to unlock.')"
    />
    
    {{-- Audio Feedback --}}
    <x-scanner.audio-feedback />
</div>
