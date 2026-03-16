<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class ScannerLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $scannerName,
        public string $userName,
        public string $reason,
        public string $scannerId,
        public int $userId,
        public array $metadata = []
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable): WebPushMessage
    {
        $reasonConfig = config("scanner-lock.reasons.{$this->reason}");
        $reasonDisplay = $reasonConfig['display_message'] ?? $this->reason;
        $severity = $reasonConfig['severity'] ?? 'medium';
        
        $icon = match($severity) {
            'high' => '❌',
            'medium' => '⚠️',
            'low' => 'ℹ️',
            default => '⚠️'
        };

        return (new WebPushMessage())
            ->title("{$icon} Scanner Locked - {$this->scannerName}")
            ->body("{$this->userName} is locked out. Reason: {$reasonDisplay}")
            ->icon('/favicon.ico')
            ->badge('/favicon.ico')
            ->tag("scanner-lock-{$this->scannerId}-{$this->userId}")
            ->renotify(true)
            ->requireInteraction(true)
            ->data([
                'url' => route('manage.scanner-locks'),
                'scanner_id' => $this->scannerId,
                'user_id' => $this->userId,
                'user_name' => $this->userName,
                'reason' => $this->reason,
                'metadata' => $this->metadata,
            ])
            ->action('Unlock Scanner', 'unlock');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'scanner_name' => $this->scannerName,
            'user_name' => $this->userName,
            'reason' => $this->reason,
            'scanner_id' => $this->scannerId,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
        ];
    }
}
