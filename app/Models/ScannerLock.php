<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ScannerLock extends Model
{
    protected $fillable = [
        'scanner_identifier',
        'locked_until',
        'reason',
        'locked_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user who is locked
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    /**
     * Check if a specific scanner is currently locked for a specific user
     */
    public static function isLocked(string $identifier, ?string $userId = null): bool
    {
        if (!$userId) {
            return false;
        }

        return Cache::remember("scanner_lock:{$identifier}:{$userId}", 10, function () use ($identifier, $userId) {
            $lock = static::where('scanner_identifier', $identifier)
                ->where('locked_by_user_id', $userId)
                ->where('locked_until', '>', now())
                ->first();

            return $lock !== null;
        });
    }

    /**
     * Get the active lock for a scanner for a specific user
     */
    public static function getActiveLock(string $identifier, ?string $userId = null): ?self
    {
        if (!$userId) {
            return null;
        }

        return static::where('scanner_identifier', $identifier)
            ->where('locked_by_user_id', $userId)
            ->where('locked_until', '>', now())
            ->first();
    }

    /**
     * Get all active locks for a scanner (for supervisors to view)
     */
    public static function getAllActiveLocks(string $identifier): \Illuminate\Support\Collection
    {
        return static::where('scanner_identifier', $identifier)
            ->where('locked_until', '>', now())
            ->get();
    }

    /**
     * Lock a scanner for a specific duration for a specific user
     * If minutes is 0, lock is unlimited and requires supervisor unlock
     */
    public static function lockScanner(string $identifier, int $minutes, ?string $reason = null, ?string $userId = null, ?array $metadata = null): self
    {
        if (!$userId) {
            throw new \InvalidArgumentException('User ID is required for scanner locks');
        }

        // Clear cache for this specific user
        Cache::forget("scanner_lock:{$identifier}:{$userId}");

        // If minutes is 0, set lock to max timestamp value (2038-01-19 for MySQL TIMESTAMP)
        // This is effectively unlimited and requires manual unlock
        $lockedUntil = $minutes === 0 
            ? Carbon::create(2038, 1, 19, 3, 14, 7) // Max TIMESTAMP value
            : now()->addMinutes($minutes);

        // Update or create lock for this user
        $lock = static::updateOrCreate(
            [
                'scanner_identifier' => $identifier,
                'locked_by_user_id' => $userId,
            ],
            [
                'locked_until' => $lockedUntil,
                'reason' => $reason,
                'metadata' => $metadata,
            ]
        );

        // Notify supervisors if this is an unlimited lock or critical lock
        if ($minutes === 0 || in_array($reason, ['cross-check-mismatch', 'manual-lock'])) {
            static::notifySupervisors($lock);
        }

        return $lock;
    }

    /**
     * Notify supervisors who have permission to unlock this scanner
     */
    protected static function notifySupervisors(self $lock): void
    {
        $config = config("scanner-lock.scanners.{$lock->scanner_identifier}");
        $unlockPermission = $config['unlock_permission'] ?? null;
        $scannerName = $config['display_name'] ?? $lock->scanner_identifier;

        if (!$unlockPermission) {
            return;
        }

        // Get all users with the unlock permission or admin role
        $supervisors = User::query()
            ->where(function ($query) use ($unlockPermission) {
                $query->whereHas('permissions', function ($q) use ($unlockPermission) {
                    $q->where('name', $unlockPermission);
                })->orWhereHas('roles', function ($q) {
                    $q->where('name', 'admin');
                });
            })
            ->get();

        // Send notification to each supervisor
        $notification = new \App\Notifications\ScannerLockedNotification(
            scannerName: $scannerName,
            userName: $lock->user->name ?? 'Unknown User',
            reason: $lock->reason ?? 'unknown',
            scannerId: $lock->scanner_identifier,
            userId: $lock->locked_by_user_id,
            metadata: $lock->metadata ?? []
        );

        foreach ($supervisors as $supervisor) {
            $supervisor->notify($notification);
        }
    }

    /**
     * Unlock a scanner for a specific user (clear the lock)
     */
    public static function unlockScanner(string $identifier, ?string $userId = null): bool
    {
        if (!$userId) {
            return false;
        }

        // Clear cache for this specific user
        Cache::forget("scanner_lock:{$identifier}:{$userId}");

        return static::where('scanner_identifier', $identifier)
            ->where('locked_by_user_id', $userId)
            ->delete() > 0;
    }

    /**
     * Get remaining seconds for the lock
     * Returns -1 for unlimited locks (year 2038)
     */
    public function getRemainingSeconds(): int
    {
        if (!$this->locked_until || now()->gte($this->locked_until)) {
            return 0;
        }

        // Check if this is an unlimited lock (year 2038)
        if ($this->locked_until->year >= 2038) {
            return -1;  // Unlimited lock indicator
        }

        return abs(now()->diffInSeconds($this->locked_until));
    }

    /**
     * Check if this lock has expired
     */
    public function isExpired(): bool
    {
        return !$this->locked_until || now()->gte($this->locked_until);
    }

    /**
     * Auto-cleanup expired locks (can be called in a scheduled task)
     */
    public static function cleanupExpired(): int
    {
        return static::where('locked_until', '<=', now())->delete();
    }
}
