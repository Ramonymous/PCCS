<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Models\ScannerLock;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

new class extends Component {
    use Toast;

    public array $activeLocks = [];
    public array $scannerTypes = [];

    public function getScannerTypesProperty(): array
    {
        $scanners = Config::get('scanner-lock.scanners', []);
        $types = [];
        
        foreach ($scanners as $identifier => $config) {
            $types[$identifier] = $config['display_name'] ?? $identifier;
        }
        
        return $types;
    }

    public function mount(): void
    {
        $this->loadActiveLocks();
    }

    public function loadActiveLocks(): void
    {
        $locks = ScannerLock::with('user:id,name,email')
            ->where('locked_until', '>', now())
            ->orderBy('locked_until', 'desc')
            ->get();

        $scannerTypes = $this->scannerTypes;
        
        $this->activeLocks = $locks->map(function ($lock) use ($scannerTypes) {
            $remainingSeconds = $lock->getRemainingSeconds();
            $scannerName = $scannerTypes[$lock->scanner_identifier] ?? $lock->scanner_identifier;
            
            // Check if lock is unlimited (-1 indicates unlimited)
            $isUnlimited = $remainingSeconds === -1;
            $displayRemaining = $isUnlimited 
                ? '∞' 
                : sprintf('%02d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60);
            
            return [
                'id' => $lock->id,
                'scanner_id' => $lock->scanner_identifier,
                'scanner_name' => $scannerName,
                'user_id' => $lock->locked_by_user_id,
                'user_name' => $lock->user->name ?? 'Unknown',
                'user_email' => $lock->user->email ?? 'N/A',
                'reason' => $lock->reason,
                'locked_until' => $isUnlimited ? 'Unlimited' : $lock->locked_until->format('d M Y H:i:s'),
                'remaining_seconds' => $remainingSeconds,
                'remaining_display' => $displayRemaining,
                'is_unlimited' => $isUnlimited,
                'metadata' => $lock->metadata,
                'can_unlock' => $this->canUnlockScanner($lock->scanner_identifier),
            ];
        })->toArray();
    }

    public function unlockUser(string $scannerId, int $userId): void
    {
        $user = Auth::user();
        
        // Admin can unlock any scanner
        if ($user->hasRole('admin')) {
            $hasPermission = true;
        } else {
            // Get permission from config
            $requiredPermission = Config::get("scanner-lock.scanners.{$scannerId}.unlock_permission");
            $hasPermission = $requiredPermission && $user->can($requiredPermission);
        }

        if (!$hasPermission) {
            $this->error(__('You do not have permission to unlock this scanner.'), null, 'toast-top');
            return;
        }

        $unlocked = ScannerLock::unlockScanner($scannerId, $userId);

        if ($unlocked) {
            $this->success(__('Scanner successfully unlocked for user.'), null, 'toast-top');
            $this->loadActiveLocks();
        } else {
            $this->error(__('Failed to unlock scanner.'), null, 'toast-top');
        }
    }

    public function canUnlockScanner(string $scannerId): bool
    {
        $user = Auth::user();
        
        // Admin can unlock any scanner
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Get permission from config
        $requiredPermission = Config::get("scanner-lock.scanners.{$scannerId}.unlock_permission");
        
        return $requiredPermission && $user->can($requiredPermission);
    }

    public function clearAllExpired(): void
    {
        $count = ScannerLock::cleanupExpired();
        $this->success(__('Successfully cleared :count expired locks.', ['count' => $count]), null, 'toast-top');
        $this->loadActiveLocks();
    }

    public function with(): array
    {
        return [];
    }
}; ?>

<div>
    <x-header :title="__('Scanner Locks Management')" :subtitle="__('Manage scanner locks for all users')" separator>
        <x-slot:middle class="!justify-end">
            <x-button 
                :label="__('Refresh')" 
                icon="o-arrow-path" 
                class="btn-sm" 
                wire:click="loadActiveLocks" 
            />
            <x-button 
                :label="__('Clear Expired')" 
                icon="o-trash" 
                class="btn-sm btn-warning" 
                wire:click="clearAllExpired" 
            />
        </x-slot:middle>
    </x-header>

    {{-- Push Notification Manager --}}
    <x-ui.push-notification-manager />

    {{-- Auto-refresh every 5 seconds --}}
    <div wire:poll.5s="loadActiveLocks"></div>

    @if(count($activeLocks) === 0)
        <x-card>
            <div class="text-center py-12">
                <x-icon name="o-lock-open" class="w-16 h-16 mx-auto mb-4 text-success opacity-50" />
                <h3 class="text-lg font-semibold mb-2">{{ __('No Active Locks') }}</h3>
                <p class="text-gray-600">{{ __('All scanners available for use.') }}</p>
            </div>
        </x-card>
    @else
        <x-card>
            <div class="space-y-4">
                @foreach($activeLocks as $lock)
                    <div class="p-4 bg-base-200 rounded-lg border-l-4 
                        @if($lock['is_unlimited']) border-error border-l-8
                        @elseif($lock['remaining_seconds'] < 60) border-warning
                        @elseif($lock['remaining_seconds'] < 180) border-info
                        @else border-error
                        @endif
                    ">
                        <div class="flex items-start justify-between gap-4">
                            {{-- Lock Info --}}
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <x-icon name="o-lock-closed" class="w-5 h-5 text-error" />
                                    <div>
                                        <div class="font-semibold text-lg">{{ $lock['scanner_name'] }}</div>
                                        <div class="text-sm text-gray-600">{{ __('Scanner ID') }}: {{ $lock['scanner_id'] }}</div>
                                    </div>
                                </div>

                                {{-- User Info --}}
                                <div class="flex items-center gap-2 mb-2">
                                    <x-icon name="o-user" class="w-4 h-4 text-gray-500" />
                                    <span class="font-medium">{{ $lock['user_name'] }}</span>
                                    <span class="text-sm text-gray-500">({{ $lock['user_email'] }})</span>
                                </div>

                                {{-- Reason --}}
                                @if($lock['reason'])
                                    @php
                                        $reasonConfig = config("scanner-lock.reasons.{$lock['reason']}");
                                        $reasonDisplay = $reasonConfig['display_message'] ?? $lock['reason'];
                                        $severity = $reasonConfig['severity'] ?? 'medium';
                                        $severityIcon = match($severity) {
                                            'high' => '❌',
                                            'medium' => '⚠️',
                                            'low' => 'ℹ️',
                                            default => '⚠️'
                                        };
                                    @endphp
                                    <div class="flex items-start gap-2 mb-2">
                                        <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning mt-0.5" />
                                        <div>
                                            <span class="text-sm font-medium">{{ __('Reason') }}: </span>
                                            <span class="text-sm">
                                                {{ $severityIcon }} {{ $reasonDisplay }}
                                            </span>
                                        </div>
                                    </div>
                                @endif

                                {{-- Metadata --}}
                                @if($lock['metadata'] && count($lock['metadata']) > 0)
                                    <div class="text-xs text-gray-500 mt-2 space-y-1">
                                        @foreach($lock['metadata'] as $key => $value)
                                            @if($key !== 'timestamp')
                                                <div>
                                                    <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span> 
                                                    {{ is_array($value) ? json_encode($value) : $value }}
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Lock Time Info --}}
                                <div class="text-xs text-gray-500 mt-2">
                                    <x-icon name="o-clock" class="w-3 h-3 inline" />
                                    {{ __('Locked until') }}: {{ $lock['locked_until'] }}
                                </div>
                            </div>

                            {{-- Countdown & Actions --}}
                            <div class="flex flex-col items-end gap-2">
                                @if($lock['is_unlimited'])
                                    {{-- Unlimited Lock Display --}}
                                    <div class="text-5xl font-bold text-error animate-pulse">
                                        ∞
                                    </div>
                                    <div class="text-xs text-error font-semibold mb-2">{{ __('UNLIMITED') }}</div>
                                    <x-badge :value="__('Supervisor Required')" class="badge-error badge-sm" />
                                @else
                                    {{-- Timed Lock Display --}}
                                    <div class="text-3xl font-bold 
                                        @if($lock['remaining_seconds'] < 60) text-warning
                                        @elseif($lock['remaining_seconds'] < 180) text-info
                                        @else text-error
                                        @endif
                                    ">
                                        {{ $lock['remaining_display'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 mb-2">{{ __('remaining') }}</div>
                                @endif
                                
                                @if($lock['can_unlock'])
                                    <x-button 
                                        :label="__('Unlock')" 
                                        icon="o-lock-open" 
                                        class="btn-sm btn-warning" 
                                        wire:click="unlockUser('{{ $lock['scanner_id'] }}', {{ $lock['user_id'] }})"
                                        spinner="unlockUser('{{ $lock['scanner_id'] }}', {{ $lock['user_id'] }})"
                                    />
                                @else
                                    <x-badge :value="__('No Permission')" class="badge-ghost badge-sm" />
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Summary Stats --}}
        <x-card class="mt-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-3xl font-bold text-error">{{ count($activeLocks) }}</div>
                    <div class="text-sm text-gray-600">{{ __('Total Active Locks') }}</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-warning">
                        {{ count(array_filter($activeLocks, fn($l) => $l['remaining_seconds'] < 180)) }}
                    </div>
                    <div class="text-sm text-gray-600">{{ __('Expiring Soon (<3 min)') }}</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-info">
                        {{ count(array_unique(array_column($activeLocks, 'user_id'))) }}
                    </div>
                    <div class="text-sm text-gray-600">{{ __('Unique Users Locked') }}</div>
                </div>
            </div>
        </x-card>
    @endif
</div>
