<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use App\Traits\Livewire\HasScannerLock;
use App\Traits\Livewire\HasCrossCheckScan;
use App\Traits\Livewire\ProcessesScan;
use App\Services\PccTraceService;
use App\Models\Customer\HPM\Pcc;
use App\Models\Customer\HPM\PccTrace;
use App\Models\Customer\HPM\PccEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use Toast, HasScannerLock, HasCrossCheckScan, ProcessesScan;

    public array $recentScans = [];
    public string $eventType = 'PRODUCTION CHECK';
    public string $remarks = '';

    // Two-stage scan state: '', 'await_ccp_qr', 'confirmation'
    public string $scanStage = '';
    public string $pendingPccBarcode = '';
    public string $pendingPccId = '';
    public string $pendingPccAlias = '';
    public string $pendingPccSlipNo = '';
    public string $pendingPccPartName = '';
    public array $ccpItems = [];
    public string $scannedCcpCode = '';

    // Scanner identifier for global locking
    private const SCANNER_ID = 'weld-production-check';

    // Override unlock permission
    protected function getUnlockPermission(): string
    {
        return 'weld.unlock-scanner';
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

            // Stage 2: Cross-check scan validation (handled by trait)
            if ($this->handleCrossCheckScan($barcode)) {
                return;
            }

            // Stage 1: Validate PCC barcode/slip and load CCPs
            $with = [
                'finishGood' => function ($q) {
                    $q->select('id', 'part_number', 'part_name', 'alias', 'type');
                },
                'finishGood.ccps' => function ($q) {
                    $q->where('is_active', true)
                        ->forStage('PRODUCTION CHECK')
                        ->select('id', 'finish_good_id', 'stage', 'check_point_img', 'revision', 'description', 'is_active');
                }
            ];
            $pcc = $this->validateAndFetchPcc($barcode, $with);
            if (!$pcc) return;

            // Skip DIRECT type finish goods (only process ASSY types)
            if (PccTraceService::isDirect($pcc)) {
                $this->warning(__("This part is DIRECT type and does not require welding process."), null, 'toast-top', 'o-exclamation-triangle', 'alert-warning', 10000);
                $this->dispatch('scan-feedback', type: 'warning');
                return;
            }

            // Validate stage transition (PRODUCTION CHECK should be first stage for ASSY)
            $trace = PccTraceService::getCurrentTrace($pcc);
            if ($trace) {
                // Already processed - validation will fail
                $this->validateStageAndCheckDuplicates($pcc, $trace, $this->eventType);
                return;
            }

            // Load CCPs and prepare for cross-check (handled by trait)
            $this->loadCcpsForPcc($pcc, 'PRODUCTION CHECK');

        } catch (\Exception $e) {
            $this->logScanError('Weld Production Check', $barcode, $e);
            $this->showGenericError();
        }
    }

    // Override trait to auto-submit upon successful cross-check match (skip confirmation overlay)
    protected function handleCrossCheckScan(string $barcode): bool
    {
        if ($this->scanStage !== 'await_ccp_qr') {
            return false;
        }

        $scannedValue = trim($barcode);

        // Empty scan -> skip check, proceed directly (keep existing behavior)
        if ($scannedValue === '') {
                $this->warning(__('Empty scan detected. Skipping checks and continuing automatically.'), null, 'toast-top', 'o-exclamation-triangle', 'alert-warning', 10000);
            $this->dispatch('scan-feedback', type: 'warning');
            $this->onCrossCheckSkipped();
            return true;
        }

        // Match -> finalize immediately (no confirmation stage / overlay)
        if ($scannedValue === $this->pendingPccAlias) {
            $this->scannedCcpCode = $scannedValue;
                $this->success(__('✓ Cross-check matched! Data saved automatically.'), null, 'toast-top');
            $this->dispatch('scan-feedback', type: 'success');
            // Directly finalize (trait previously moved to confirmation stage)
            $this->onConfirmScan();
            return true;
        }

        // Mismatch -> replicate trait behavior (lock scanner)
        \Log::warning('Cross-check mismatch attempt', [
            'scanner_id' => static::SCANNER_ID ?? 'unknown',
            'user_id' => \Auth::id(),
            'expected_alias' => $this->pendingPccAlias,
            'scanned_value' => $scannedValue,
            'pending_pcc_id' => $this->pendingPccId,
        ]);

        // Lock duration is now determined by config (scanner-lock.reasons.cross-check-mismatch)
        $this->lockScanner(0, 'cross-check-mismatch', [
            'pending_pcc_id' => $this->pendingPccId,
            'expected_value' => $this->pendingPccAlias,
        ]);

        return true;
    }

    // Finalize the check by creating PccTrace and PccEvent
    public function finalizeCcpCheck(?Pcc $pcc = null): void
    {
        if (!$pcc) {
            $pcc = Pcc::find($this->pendingPccId);
            if (!$pcc) {
                    $this->error(__('Label not found during confirmation.'), null, 'toast-top');
                $this->dispatch('scan-feedback', type: 'error');
                $this->resetScanState();
                return;
            }
        }

        try {
            DB::beginTransaction();

            // Current state (PccTrace)
            $trace = PccTrace::create([
                'pcc_id' => $pcc->id,
                'event_type' => $this->eventType,
                'event_timestamp' => now(),
                'remarks' => $this->remarks ?: null,
            ]);

            // Historical log (PccEvent)
            PccEvent::create([
                'pcc_trace_id' => $trace->id,
                'event_users' => Auth::id(),
                'event_type' => $trace->event_type,
                'event_timestamp' => $trace->event_timestamp,
                'remarks' => $trace->remarks,
            ]);

            DB::commit();

            $partNumber = $pcc->finishGood->part_number ?? $pcc->part_no;
            $this->success("✓ {$partNumber} - {$pcc->slip_no}", null, 'toast-top');
            $this->dispatch('scan-feedback', type: 'success');
            
            // Notify trace page for live updates
            $this->dispatch('pcc-trace-updated', pccId: $pcc->id);
            
            $this->loadRecentScans();
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detailed error for debugging
            \Log::error('Weld Production Check - Finalization failed', [
                'user_id' => \Auth::id(),
                'pcc_id' => $this->pendingPccId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
                $this->error(__('A system error occurred while saving data. Please try again.'), null, 'toast-top');
            $this->dispatch('scan-feedback', type: 'error');
        } finally {
            $this->resetScanState();
        }
    }

    // Implement trait's abstract method
    protected function onConfirmScan(): void
    {
        $this->finalizeCcpCheck();
    }

    public function clearRemarks(): void
    {
        $this->remarks = '';
    }

    // No dynamic data needed for view
    public function with(): array { return []; }
}; ?>

<div>
    <x-header :title="__('Weld Scanner - Production Check')" separator>
        <x-slot:middle class="!justify-end">
            <x-button :label="__('Refresh')" icon="o-arrow-path" class="btn-sm" wire:click="loadRecentScans" />
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Scanner Section --}}
        <div class="space-y-4">
            {{-- QR Scanner Component --}}
                <livewire:components.ui.qr-scanner 
                    scanner-id="weld-scanner"
                :label="__('Scanner')"
                    :placeholder="__('Scan or type barcode/slip number...')"
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

    {{-- CCP Confirmation Overlay removed: auto-submit on match --}}

    {{-- Scanner Lock Overlay --}}
    <x-scanner.lock-overlay 
        :show="$this->scannerLocked"
        :lockRemainingSeconds="$this->lockRemainingSeconds"
        :canUnlock="auth()->user() && auth()->user()->can('weld.unlock-scanner')"
            :title="$this->activeLock && $this->activeLock->reason === 'cross-check-mismatch' ? __('❌ Cross-Check Failed') : __('Scanner Locked')"
            :subtitle="$this->activeLock && $this->activeLock->reason === 'cross-check-mismatch' ? __('Physical scan does not match PCC label.') : __('Scanner temporarily inactive.')"
            :alertMessage="$this->activeLock && $this->activeLock->reason === 'cross-check-mismatch' ? __('Physical scan does not match expected Part Number: :expected. Double-check the label and physical part being inspected!', ['expected' => $this->activeLock->metadata['expected_value'] ?? 'N/A']) : __('Scanner locked for system security. Contact supervisor if unlock is needed.')"
            :footerMessage="__('Please ensure physical scan matches the Part Number on the PCC label before trying again.')"
    />

    {{-- Audio Feedback --}}
    <x-scanner.audio-feedback />
</div>
