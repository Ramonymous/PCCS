<?php

namespace App\Traits\Livewire;

use App\Models\ScannerLock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

/**
 * Trait for managing per-user scanner locks in Livewire components
 * 
 * Usage:
 * 1. Add trait to component: use HasScannerLock;
 * 2. Define SCANNER_ID constant: private const SCANNER_ID = 'your-scanner-id';
 * 3. Access via computed properties: $this->scannerLocked, $this->activeLock, $this->lockRemainingSeconds
 * 4. Lock scanner: $this->lockScanner(0, 'reason-name', ['metadata']) - 0 minutes uses config
 * 
 * Configuration:
 * - Scanner lock behavior is configured in config/scanner-lock.php
 * - Pass 0 as minutes to use configured duration based on reason or scanner default
 * - Lock durations can be customized per scanner type or per reason
 * - Supervisor unlock requirement can be toggled per scanner
 * 
 * Note: Locks are now per-user. Only the locked user sees the lock overlay.
 * Users with unlock permission can see and unlock any user's lock.
 */
trait HasScannerLock
{
    /**
     * Check if scanner is locked for current user
     */
    public function getScannerLockedProperty(): bool
    {
        $userId = Auth::id();
        return ScannerLock::isLocked(static::SCANNER_ID, $userId);
    }

    /**
     * Get the active lock instance for current user
     */
    public function getActiveLockProperty(): ?ScannerLock
    {
        $userId = Auth::id();
        return ScannerLock::getActiveLock(static::SCANNER_ID, $userId);
    }

    /**
     * Get all active locks for this scanner (for supervisors)
     */
    public function getAllLocksProperty(): \Illuminate\Support\Collection
    {
        return ScannerLock::getAllActiveLocks(static::SCANNER_ID);
    }

    /**
     * Remaining seconds for the current lock window
     */
    public function getLockRemainingSecondsProperty(): int
    {
        $lock = $this->activeLock;
        return $lock ? $lock->getRemainingSeconds() : 0;
    }

    /**
     * Poller to auto-unlock when time has passed
     */
    public function checkLock(): void
    {
        $lock = $this->activeLock;
        if ($lock && $lock->isExpired()) {
            ScannerLock::unlockScanner(static::SCANNER_ID, Auth::id());
        }
    }

    /**
     * Manually unlock scanner (permission required)
     * Override getUnlockPermission() to customize permission check
     */
    public function unlockScanner(): void
    {
        $user = Auth::user();
        $permission = $this->getUnlockPermission();
        
        if ($user && method_exists($user, 'can') && $user->can($permission)) {
            ScannerLock::unlockScanner(static::SCANNER_ID, $user->id);
            $this->success('Scanner Anda telah dibuka.', position: 'toast-top');
            $this->dispatch('scan-feedback', type: 'success');
            return;
        }
        
        $this->error('Anda tidak memiliki izin untuk membuka kunci scanner.', position: 'toast-top');
        $this->dispatch('scan-feedback', type: 'error');
    }

    /**
     * Lock scanner for N minutes with reason (for current user only)
     * If minutes is 0, uses configured default duration
     */
    protected function lockScanner(int $minutes = 0, string $reason = '', array $metadata = []): void
    {
        $userId = Auth::id();
        
        // If minutes is 0, get duration from config based on reason or scanner default
        if ($minutes === 0) {
            $minutes = $this->getLockDuration($reason);
        }
        
        // Validate against max duration
        $maxDuration = $this->getMaxLockDuration();
        if ($maxDuration > 0 && $minutes > $maxDuration) {
            $minutes = $maxDuration;
        }
        
        ScannerLock::lockScanner(
            static::SCANNER_ID,
            $minutes,
            $reason,
            $userId,
            array_merge(['timestamp' => now()->toIso8601String()], $metadata)
        );

        $toastDuration = Config::get('scanner-lock.ui.toast_duration_ms', 10000);
        
        // Different message for unlimited vs timed locks
        if ($minutes === 0) {
            $this->error("⛔ Scanner Anda TERKUNCI! Hubungi supervisor untuk membuka kunci.", position: 'toast-top', timeout: $toastDuration);
        } else {
            $this->warning("Scanner Anda dikunci selama {$minutes} menit.", position: 'toast-top', timeout: $toastDuration);
        }
        
        $this->dispatch('scan-feedback', type: 'warning');
    }
    
    /**
     * Get lock duration based on reason or default
     */
    protected function getLockDuration(string $reason = ''): int
    {
        // Check if reason has configured duration
        if ($reason && Config::has("scanner-lock.reasons.{$reason}.duration_minutes")) {
            return Config::get("scanner-lock.reasons.{$reason}.duration_minutes");
        }
        
        // Check scanner-specific default
        if (Config::has("scanner-lock.scanners." . static::SCANNER_ID . ".lock_duration_minutes")) {
            return Config::get("scanner-lock.scanners." . static::SCANNER_ID . ".lock_duration_minutes");
        }
        
        // Fall back to global default
        return Config::get('scanner-lock.defaults.lock_duration_minutes', 5);
    }
    
    /**
     * Get max lock duration for this scanner
     */
    protected function getMaxLockDuration(): int
    {
        // Check scanner-specific max
        if (Config::has("scanner-lock.scanners." . static::SCANNER_ID . ".max_lock_duration_minutes")) {
            return Config::get("scanner-lock.scanners." . static::SCANNER_ID . ".max_lock_duration_minutes");
        }
        
        // Fall back to global default
        return Config::get('scanner-lock.defaults.max_lock_duration_minutes', 30);
    }
    
    /**
     * Check if this scanner requires supervisor unlock
     */
    protected function requiresSupervisorUnlock(): bool
    {
        // Check scanner-specific setting
        if (Config::has("scanner-lock.scanners." . static::SCANNER_ID . ".requires_supervisor_unlock")) {
            return Config::get("scanner-lock.scanners." . static::SCANNER_ID . ".requires_supervisor_unlock");
        }
        
        // Fall back to global default
        return Config::get('scanner-lock.defaults.requires_supervisor_unlock', true);
    }

    /**
     * Check lock state and auto-cleanup if expired
     * Returns true if locked and not expired
     */
    protected function checkAndCleanupLock(): bool
    {
        $lock = $this->activeLock;
        
        if ($lock && !$lock->isExpired()) {
            $remaining = $lock->getRemainingSeconds();
            $this->warning('Scanner Anda terkunci. Tunggu ' . ceil($remaining / 60) . ' menit lagi.', position: 'toast-top', timeout: 10000);
            $this->dispatch('scan-feedback', type: 'warning');
            return true;
        }

        // Auto-cleanup expired lock
        if ($lock && $lock->isExpired()) {
            ScannerLock::unlockScanner(static::SCANNER_ID, Auth::id());
        }

        return false;
    }

    /**
     * Override this method to customize unlock permission
     * Default pattern: {module}.unlock-scanner
     */
    protected function getUnlockPermission(): string
    {
        return 'unlock-scanner';
    }
}
