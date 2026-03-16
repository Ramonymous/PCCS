<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use App\Traits\Livewire\ProcessesScan;
use App\Services\PccTraceService;
use App\Models\Customer\HPM\Pcc;
use App\Models\Customer\HPM\PccTrace;
use App\Models\Customer\HPM\PccEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use Toast, ProcessesScan;

    public array $recentScans = [];
    public string $eventType = 'RECEIVED';
    public string $remarks = '';
    
    // Modal state
    public bool $showModal = false;
    public ?array $pendingPcc = null;

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
            // Validate and fetch PCC
            $with = ['finishGood:id,alias,part_number,wh_address,type'];
            $pcc = $this->validateAndFetchPcc($barcode, $with);
            if (!$pcc) return;

            // Get current trace and validate stage transition
            $trace = PccTraceService::getCurrentTrace($pcc);
            $trace = $this->validateStageAndCheckDuplicates($pcc, $trace, $this->eventType);
            if (!$trace) return;

            // Store pending PCC data and show modal for warehouse address confirmation
            $this->pendingPcc = [
                'id' => $pcc->id,
                'trace_id' => $trace->id,
                'slip_no' => $pcc->slip_no,
                'part_no' => $pcc->part_no,
                'part_name' => $pcc->part_name,
                'wh_address' => $pcc->finishGood->wh_address ?? 'N/A',
            ];
            
            $this->showModal = true;
            $this->dispatch('scan-feedback', type: 'success');

        } catch (\Exception $e) {
            $this->logScanError('Received', $barcode, $e);
            $this->showGenericError();
        }
    }

    public function confirmSubmit(): void
    {
        if (!$this->pendingPcc) {
            $this->error(__('Invalid data.'), null, 'toast-top');
            return;
        }

        try {
            DB::beginTransaction();

            // Get trace record
            $trace = PccTrace::find($this->pendingPcc['trace_id']);
            if (!$trace) {
                $this->error(__('Trace not found.'), null, 'toast-top');
                $this->closeModal();
                return;
            }

            // Get PCC with ship quantity
            $pcc = Pcc::with('finishGood:id,alias,part_number,stock')
                ->find($this->pendingPcc['id']);
            
            if (!$pcc) {
                $this->error(__('PCC not found.'), null, 'toast-top');
                $this->closeModal();
                DB::rollBack();
                return;
            }

            // Update PccTrace to new stage (current label state)
            $trace->update([
                'event_type' => $this->eventType,
                'event_timestamp' => now(),
                'remarks' => $this->remarks ?: null,
            ]);

            // Log to PccEvent (historical log)
            PccEvent::create([
                'pcc_trace_id' => $trace->id,
                'event_users' => Auth::id(),
                'event_type' => $trace->event_type,
                'event_timestamp' => $trace->event_timestamp,
                'remarks' => $trace->remarks,
            ]);

            // Add stock to finish good (RECEIVED = incoming stock)
            if ($pcc->finishGood && $pcc->ship) {
                $pcc->finishGood->increment('stock', $pcc->ship);
                
                \Log::info('Stock added on RECEIVED', [
                    'pcc_id' => $pcc->id,
                    'slip_no' => $pcc->slip_no,
                    'part_number' => $pcc->finishGood->part_number,
                    'quantity_added' => $pcc->ship,
                    'new_stock' => $pcc->finishGood->fresh()->stock,
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();

            $stockInfo = $pcc->ship ? " (+{$pcc->ship} stock)" : '';
            $this->success("✓ {$this->pendingPcc['part_no']} - {$this->pendingPcc['slip_no']}{$stockInfo}", null, 'toast-top');
            
            // Notify trace page for live updates
            $this->dispatch('pcc-trace-updated', pccId: $this->pendingPcc['id']);
            
            // Reload recent scans
            $this->loadRecentScans();
            
            $this->closeModal();

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detailed error for debugging
            \Log::error('Received - Confirmation failed', [
                'user_id' => Auth::id(),
                'pcc_id' => $this->pendingPcc['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error(__('A system error occurred while saving data. Please try again.'), null, 'toast-top');
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->pendingPcc = null;
    }

    public function clearRemarks(): void
    {
        $this->remarks = '';
    }

    public function with(): array { return []; }
}; ?>

<div>
    <x-header :title="__('HPM PC-Store')" :subtitle="__('Scanner Warehouse HPM')" separator>
        <x-slot:middle class="!justify-end">
            <x-button :label="__('Refresh')" icon="o-arrow-path" class="btn-sm" wire:click="loadRecentScans" />
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Scanner Section --}}
        <div class="space-y-4">
            {{-- QR Scanner Component --}}
            <livewire:components.ui.qr-scanner 
                scanner-id="warehouse-scanner"
                :label="__('Scanner')"
                :placeholder="__('Scan atau ketik barcode/slip number...')"
                :show-manual-input="true"
                :cooldown-seconds="3"
            />
        </div>

        {{-- Recent Scans Section --}}
        <div>
            <x-scanner.recent-scans :recentScans="$recentScans" :title="__('Recent Scans')" />
        </div>
    </div>

    {{-- Warehouse Address Confirmation Modal --}}
    <x-modal wire:model="showModal" :title="__('Konfirmasi Lokasi Penyimpanan')" persistent>
        @if($pendingPcc)
            <div class="space-y-4">
                {{-- Part Information --}}
                <div class="bg-base-200 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="text-gray-600">{{ __('Slip No') }}:</div>
                        <div class="font-semibold">{{ $pendingPcc['slip_no'] }}</div>
                        
                        <div class="text-gray-600">{{ __('Part No') }}:</div>
                        <div class="font-semibold">{{ $pendingPcc['part_no'] }}</div>
                        
                        <div class="text-gray-600">{{ __('Part Name') }}:</div>
                        <div class="font-semibold col-span-2 mt-1">{{ $pendingPcc['part_name'] }}</div>
                    </div>
                </div>

                {{-- Warehouse Address (Main Focus) --}}
                <div class="bg-warning/10 border-2 border-warning p-6 rounded-lg">
                    <div class="text-center">
                        <div class="text-sm text-gray-600 mb-2">{{ __('Simpan di lokasi') }}:</div>
                        <div class="text-3xl font-bold text-warning">{{ $pendingPcc['wh_address'] }}</div>
                    </div>
                </div>

                {{-- Warning Message --}}
                <div class="alert alert-warning">
                    <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                    <span>{{ __('Pastikan part disimpan di lokasi yang benar sebelum konfirmasi!') }}</span>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.closeModal()" />
            <x-button :label="__('Konfirmasi')" class="btn-primary" wire:click="confirmSubmit" />
        </x-slot:actions>
    </x-modal>
    
    {{-- Audio Feedback --}}
    <x-scanner.audio-feedback />
</div>
