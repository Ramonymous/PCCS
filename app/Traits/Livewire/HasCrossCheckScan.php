<?php

namespace App\Traits\Livewire;

use App\Models\Customer\HPM\Pcc;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for managing two-stage cross-check scan workflow
 * 
 * Workflow:
 * 1. Stage 1 (empty): Scan PCC barcode → validate → load CCPs → move to 'await_ccp_qr'
 * 2. Stage 2 (await_ccp_qr): Scan physical part → cross-check against alias → move to 'confirmation'
 * 3. Stage 3 (confirmation): Show CCP overlay → user confirms → finalize
 * 
 * Usage:
 * 1. Add trait to component: use HasCrossCheckScan;
 * 2. Define required properties in component (see below)
 * 3. Implement abstract methods: validatePccForStage(), processValidatedPcc()
 */
trait HasCrossCheckScan
{
    // Required properties (define in component):
    // public string $scanStage = '';
    // public string $pendingPccBarcode = '';
    // public string $pendingPccId = '';
    // public string $pendingPccAlias = '';
    // public string $pendingPccSlipNo = '';
    // public string $pendingPccPartName = '';
    // public array $ccpItems = [];
    // public string $scannedCcpCode = '';

    /**
     * Handle Stage 2: Cross-check scan validation
     */
    protected function handleCrossCheckScan(string $barcode): bool
    {
        if ($this->scanStage !== 'await_ccp_qr') {
            return false;
        }

        $scannedValue = trim($barcode);

        // Empty scan -> skip check, proceed directly
        if ($scannedValue === '') {
            $this->warning('Scan kosong. Melewati pemeriksaan dan melanjutkan.', position: 'toast-top', timeout: 10000);
            $this->dispatch('scan-feedback', type: 'warning');
            $this->onCrossCheckSkipped();
            return true;
        }

        // Cross-check: scanned value MUST match PCC alias
        if ($scannedValue === $this->pendingPccAlias) {
            $this->scannedCcpCode = $scannedValue;
            $this->scanStage = 'confirmation';
            $this->success('✓ Cross-check cocok! Periksa CCP di bawah, lalu konfirmasi.', position: 'toast-top');
            $this->dispatch('scan-feedback', type: 'success');
            return true;
        }

        // Mismatch -> log and lock scanner
        Log::warning('Cross-check mismatch attempt', [
            'scanner_id' => static::SCANNER_ID ?? 'unknown',
            'user_id' => Auth::id(),
            'expected_alias' => $this->pendingPccAlias,
            'scanned_value' => $scannedValue,
            'pending_pcc_id' => $this->pendingPccId,
        ]);

        // Lock duration determined by config (scanner-lock.reasons.cross-check-mismatch)
        $this->lockScanner(0, 'cross-check-mismatch', [
            'pending_pcc_id' => $this->pendingPccId,
            'expected_value' => $this->pendingPccAlias,
        ]);

        return true;
    }

    /**
     * Load CCPs for a given PCC and stage
     */
    protected function loadCcpsForPcc(Pcc $pcc, string $stage): void
    {
        // Store PCC data
        $this->pendingPccBarcode = (string) ($pcc->slip_barcode ?? $pcc->slip_no);
        $this->pendingPccId = (string) $pcc->id;
        $this->pendingPccAlias = (string) ($pcc->finishGood->part_number ?? $pcc->part_no);
        $this->pendingPccSlipNo = (string) $pcc->slip_no;
        $this->pendingPccPartName = (string) $pcc->part_name;

        // Determine active CCPs for this part via FinishGood
        $activeCcps = collect();
        if ($pcc->relationLoaded('finishGood') && $pcc->finishGood) {
            $activeCcps = ($pcc->finishGood->ccps ?? collect())
                ->filter(fn($c) => (bool) $c->is_active)
                ->values();
        }

        // Prepare CCP items
        if ($activeCcps && $activeCcps->count() > 0) {
            $this->ccpItems = $activeCcps->map(function ($ccp) {
                return [
                    'id' => $ccp->id,
                    'img' => $ccp->check_point_img ? Storage::url('hpm/ccp/' . $ccp->check_point_img) : null,
                    'revision' => $ccp->revision,
                    'description' => $ccp->description,
                ];
            })->toArray();
        } else {
            $this->ccpItems = [];
        }

        // Move to Stage 2
        $this->scanStage = 'await_ccp_qr';
        $this->warning('Scan berhasil! Sekarang lakukan scan fisik untuk cross-check (harus sama dengan label: ' . $this->pendingPccAlias . ')', position: 'toast-top', timeout: 0);
        $this->dispatch('scan-feedback', type: 'warning');
    }

    /**
     * Reset all scan state properties
     */
    protected function resetScanState(): void
    {
        $this->scanStage = '';
        $this->pendingPccBarcode = '';
        $this->pendingPccId = '';
        $this->pendingPccAlias = '';
        $this->pendingPccSlipNo = '';
        $this->pendingPccPartName = '';
        $this->ccpItems = [];
        $this->scannedCcpCode = '';
    }

    /**
     * Confirm scan in confirmation stage
     */
    public function confirmScan(): void
    {
        if ($this->scanStage === 'confirmation') {
            $this->onConfirmScan();
        }
    }

    /**
     * Cancel current scan
     */
    public function cancelScan(): void
    {
        $this->resetScanState();
        $this->warning('Scan dibatalkan.', position: 'toast-top', timeout: 10000);
        $this->dispatch('scan-feedback', type: 'warning');
    }

    /**
     * Hook: Called when cross-check is skipped (empty scan)
     * Override in component to implement custom logic
     */
    protected function onCrossCheckSkipped(): void
    {
        // Default: proceed to confirmation
        $this->onConfirmScan();
    }

    /**
     * Hook: Called when user confirms in confirmation stage
     * Override in component to implement finalization logic
     */
    abstract protected function onConfirmScan(): void;
}
