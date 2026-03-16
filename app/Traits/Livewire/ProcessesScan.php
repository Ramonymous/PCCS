<?php

namespace App\Traits\Livewire;

use App\Services\PccTraceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trait for common scan processing logic across all scanner pages
 * 
 * Provides reusable validation, error handling, and logging for barcode scanning workflows.
 * 
 * Usage:
 * 1. Add trait to component: use ProcessesScan;
 * 2. Define required properties: public string $eventType
 * 3. Call validateAndFetchPcc() or validateStageAndCheckDuplicates() in processScan()
 */
trait ProcessesScan
{
    /**
     * Validate barcode and fetch PCC with given relationships.
     * Returns null if validation fails (error already dispatched).
     */
    protected function validateAndFetchPcc(string $barcode, array $with = []): ?\App\Models\Customer\HPM\Pcc
    {
        $pcc = PccTraceService::findPccByBarcodeOrSlip($barcode, $with);

        if (!$pcc) {
            $this->error(__('Label not found in the system!'), null, 'toast-top');
            $this->dispatch('scan-feedback', type: 'error');
            return null;
        }

        return $pcc;
    }

    /**
     * Validate stage transition and check for duplicate scans.
     * Returns trace if validation passes, null otherwise (warning already dispatched).
     */
    protected function validateStageAndCheckDuplicates(
        \App\Models\Customer\HPM\Pcc $pcc,
        ?\App\Models\Customer\HPM\PccTrace $trace,
        string $eventType,
        ?bool $isDirect = null,
        bool $lockOnInvalidStage = false
    ): ?\App\Models\Customer\HPM\PccTrace {
        $isDirect = $isDirect ?? PccTraceService::isDirect($pcc);

        // Validate stage transition
        $validation = PccTraceService::validateStageTransition($trace, $eventType, $isDirect);
        if (!$validation['valid']) {
            $this->warning(__($validation['message']), null, 'toast-top', 'o-exclamation-triangle', 'alert-warning', 10000);
            $this->dispatch('scan-feedback', type: 'warning');
            
            if ($lockOnInvalidStage && $trace) {
                Log::warning("{$eventType} - Invalid stage attempt", [
                    'user_id' => Auth::id(),
                    'pcc_id' => $pcc->id,
                    'current_stage' => $trace->event_type,
                    'expected_stage' => $validation['expected'] ?? 'N/A',
                    'type' => $isDirect ? 'DIRECT' : 'ASSY',
                ]);
                
                if (method_exists($this, 'lockScanner')) {
                    $this->lockScanner(0, 'invalid-stage');
                }
            }
            
            return null;
        }

        // Check for duplicate scans (only if trace exists)
        if ($trace) {
            $recentEvent = PccTraceService::getRecentEvent($trace, $eventType, 5);
            
            if ($recentEvent) {
                $this->warning(__("This label was already scanned for ':event' within the last 5 minutes!", ['event' => $eventType]), null, 'toast-top', 'o-exclamation-triangle', 'alert-warning', 10000);
                $this->dispatch('scan-feedback', type: 'warning');
                
                Log::warning("{$eventType} - Duplicate scan attempt", [
                    'user_id' => Auth::id(),
                    'pcc_id' => $pcc->id,
                    'recent_event_timestamp' => $recentEvent->event_timestamp,
                ]);
                
                if (method_exists($this, 'lockScanner')) {
                    $this->lockScanner(0, 'duplicate-scan');
                }
                
                return null;
            }
        }

        return $trace;
    }

    /**
     * Log scan processing error with context
     */
    protected function logScanError(string $context, string $barcode, \Exception $e): void
    {
        Log::error("{$context} - Scan processing failed", [
            'user_id' => Auth::id(),
            'barcode' => $barcode,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Show generic error to user (for exception handling)
     */
    protected function showGenericError(): void
    {
        $this->error(__('A system error occurred. Please try again or contact the administrator.'), null, 'toast-top');
        $this->dispatch('scan-feedback', type: 'error');
    }
}
