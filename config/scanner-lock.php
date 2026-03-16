<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scanner Lock Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file defines the behavior of scanner locks across
    | different scanner types in the system. Each scanner can have custom
    | settings for lock duration and unlock requirements.
    |
    */

    /**
     * Default lock settings applied to all scanners unless overridden
     */
    'defaults' => [
        /**
         * Whether supervisor approval is required to unlock
         * If true, only users with the appropriate unlock permission can manually unlock
         * If false, locks expire automatically without requiring supervisor intervention
         */
        'requires_supervisor_unlock' => true,

        /**
         * Default lock duration in minutes
         * Set to 0 for unlimited duration (requires manual unlock)
         * Common values: 2-5 minutes for temporary locks
         */
        'lock_duration_minutes' => 0,

        /**
         * Maximum lock duration in minutes (safety limit)
         * Prevents locks from being set too long
         * Set to 0 for no limit
         */
        'max_lock_duration_minutes' => 0,
    ],

    /**
     * Scanner-specific configurations
     * Override defaults for specific scanner types
     * 
     * Available scanner identifiers:
     * - weld-production-check
     * - qa-pdi-check
     * - delivery-scanner
     */
    'scanners' => [
        'weld-production-check' => [
            'requires_supervisor_unlock' => true,
            'lock_duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'max_lock_duration_minutes' => 0,  // No limit
            
            // Permission required to unlock this scanner
            'unlock_permission' => 'weld.unlock-scanner',
            
            // Display name for UI
            'display_name' => 'Weld - Production Check',
            
            // Description
            'description' => 'Scanner untuk verifikasi produksi welding',
        ],

        'qa-pdi-check' => [
            'requires_supervisor_unlock' => true,
            'lock_duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'max_lock_duration_minutes' => 0,  // No limit
            
            'unlock_permission' => 'qa.unlock-scanner',
            'display_name' => 'QA - PDI Check',
            'description' => 'Scanner untuk quality assurance dan PDI check',
        ],

        'delivery-scanner' => [
            'requires_supervisor_unlock' => true,
            'lock_duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'max_lock_duration_minutes' => 0,  // No limit
            
            'unlock_permission' => 'delivery.unlock-scanner',
            'display_name' => 'PPIC - Delivery',
            'description' => 'Scanner untuk proses delivery oleh PPIC',
        ],
    ],

    /**
     * Lock reason configurations
     * Define lock durations for specific error reasons
     */
    'reasons' => [
        'cross-check-mismatch' => [
            'duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'display_message' => 'Cross-check tidak cocok',
            'severity' => 'high',
        ],
        
        'duplicate-scan' => [
            'duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'display_message' => 'Duplikat terdeteksi',
            'severity' => 'medium',
        ],
        
        'invalid-stage-early' => [
            'duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'display_message' => 'Tahap tidak valid (terlalu awal)',
            'severity' => 'medium',
        ],
        
        'invalid-stage' => [
            'duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'display_message' => 'Tahap tidak valid',
            'severity' => 'medium',
        ],
        
        'already-delivered' => [
            'duration_minutes' => 0,  // Unlimited - requires supervisor unlock
            'display_message' => 'Label sudah terkirim',
            'severity' => 'high',
        ],
    ],

    /**
     * Auto-cleanup settings
     */
    'cleanup' => [
        /**
         * Enable automatic cleanup of expired locks
         */
        'auto_cleanup_enabled' => true,

        /**
         * Cleanup interval in minutes
         * How often should the system check for expired locks
         */
        'cleanup_interval_minutes' => 15,

        /**
         * Days to keep expired lock history
         * Set to 0 to delete immediately after expiration
         */
        'keep_expired_days' => 7,
    ],

    /**
     * UI/UX Settings
     */
    'ui' => [
        /**
         * Auto-refresh interval for lock status (in seconds)
         * Used by Livewire wire:poll
         */
        'refresh_interval_seconds' => 5,

        /**
         * Warning threshold in seconds
         * Show warning color when lock has less than this time remaining
         */
        'warning_threshold_seconds' => 60,

        /**
         * Toast notification duration in milliseconds
         */
        'toast_duration_ms' => 10000,
    ],

];
