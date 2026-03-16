<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public bool $isScanning = false;
    public string $label = 'QR/Barcode Scanner';
    public string $placeholder = 'Scan atau ketik barcode...';
    public bool $showManualInput = true;
    public bool $autoStart = false;
    public bool $stopAfterScan = false; // Auto-stop after successful scan
    public int $cooldownSeconds = 3; // Cooldown period after scan (prevents rapid duplicate scans)
    public string $facingMode = 'environment'; // 'user' for front camera, 'environment' for back camera
    public string $scannerId = 'default';

    public function mount(): void
    {
        if ($this->autoStart) {
            $this->isScanning = true;
        }
    }

    public function destroy(): void
    {
        // Cleanup when component is destroyed
        $this->isScanning = false;
    }

    public function startScanning(): void
    {
        $this->isScanning = true;
        $this->dispatch('scanner-started');
    }

    public function stopScanning(): void
    {
        $this->isScanning = false;
        $this->dispatch('scanner-stopped');
    }

    public function handleScan(string $barcodeData): void
    {
        // Auto-stop scanner after successful scan if enabled
        if ($this->stopAfterScan) {
            $this->isScanning = false;
        }
        
        // Dispatch event to parent component
        $this->dispatch('barcode-scanned', barcode: $barcodeData);
    }
}; ?>

<div data-scanner-id="{{ $scannerId }}">
    <x-card :title="$label" shadow>
        {{-- Camera Controls --}}
        <div class="flex gap-2 mb-4">
            <x-button 
                label="Start Scan" 
                icon="o-camera"
                class="btn-primary flex-1"
                x-show="!$wire.isScanning"
                @click="$wire.startScanning(); (function(s){ window['startScanner' + s](); })($el.closest('[data-scanner-id]').dataset.scannerId)"
            />
            <x-button 
                label="Stop Scan" 
                icon="o-stop"
                class="btn-error flex-1"
                x-show="$wire.isScanning"
                @click="$wire.stopScanning(); (function(s){ window['stopScanner' + s](); })($el.closest('[data-scanner-id]').dataset.scannerId)"
            />
        </div>

        {{-- Video Canvas --}}
        <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 4/3;">
            <video id="scanner-video-{{ $scannerId }}" class="w-full h-full object-cover hidden"></video>
            <canvas id="scanner-canvas-{{ $scannerId }}" class="w-full h-full"></canvas>
            
            {{-- Scan Indicator --}}
            <div 
                id="scan-indicator-{{ $scannerId }}" 
                class="absolute inset-0 pointer-events-none hidden"
                x-show="$wire.isScanning"
            >
                <div class="absolute inset-4 border-2 border-green-500 rounded-lg shadow-lg shadow-green-500/50"></div>
                <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-red-500 animate-pulse"></div>
            </div>

            {{-- Status Overlay --}}
            <div 
                class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 p-3"
                x-show="$wire.isScanning"
            >
                <p class="text-white text-sm text-center">
                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></span>
                    {{ __('Point the camera at the barcode/QR code') }}
                </p>
            </div>

            {{-- Not Scanning Overlay --}}
            <div 
                class="absolute inset-0 flex items-center justify-center bg-black/50"
                x-show="!$wire.isScanning"
            >
                <div class="text-center text-white">
                    <x-icon name="o-qr-code" class="w-16 h-16 mx-auto mb-2 opacity-70" />
                    <p class="text-sm">Klik "Start Scan" untuk memulai</p>
                </div>
            </div>
        </div>

        {{-- Manual Input Fallback --}}
        @if($showManualInput)
            <div class="mt-4">
                <x-input 
                    :label="$placeholder" 
                    :placeholder="$placeholder"
                    icon="o-qr-code"
                    x-ref="manualInput{{ $scannerId }}"
                    @keydown.enter="(function(s,e){ window['handleManualInput' + s](e); })($el.closest('[data-scanner-id]').dataset.scannerId, $event)"
                />
            </div>
        @endif

    </x-card>

    {{-- jsQR Scanner Script --}}
    @once
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    @endonce

    <script>
        (function() {
            const scannerId = '{{ $scannerId }}';
            const cooldownMs = {{ $cooldownSeconds * 1000 }};
            let video = null;
            let canvas = null;
            let canvasContext = null;
            let lastScannedCode = null;
            let lastScanTime = 0;
            let animationFrameId = null;
            let isInCooldown = false;

            window['startScanner' + scannerId] = function() {
                video = document.getElementById('scanner-video-' + scannerId);
                canvas = document.getElementById('scanner-canvas-' + scannerId);
                canvasContext = canvas.getContext('2d', { willReadFrequently: true });

                // Safety: stop any existing stream bound to this video
                try {
                    if (video && video.srcObject) {
                        const oldStream = video.srcObject;
                        oldStream.getTracks().forEach(t => t.stop());
                        video.pause();
                        video.srcObject = null;
                    }
                } catch (e) { /* noop */ }

                const initAndScan = () => {
                    try {
                        if (!video) return;
                        // Sometimes metadata is ready but width is 0 for a moment
                        const vw = video.videoWidth || canvas.width;
                        const vh = video.videoHeight || canvas.height;
                        if (vw && vh) {
                            canvas.width = vw;
                            canvas.height = vh;
                            const indicator = document.getElementById('scan-indicator-' + scannerId);
                            if (indicator) {
                                indicator.classList.remove('hidden');
                            }
                            if (animationFrameId) cancelAnimationFrame(animationFrameId);
                            scanFrame();
                            return true;
                        }
                        return false;
                    } catch (_) { return false; }
                };

                // Request camera access
                navigator.mediaDevices.getUserMedia({
                    video: { facingMode: '{{ $facingMode }}' }
                })
                .then(stream => {
                    video.srcObject = stream;
                    video.setAttribute('playsinline', true);
                    const onLoaded = () => {
                        initAndScan();
                        video.removeEventListener('loadedmetadata', onLoaded);
                    };
                    const onPlaying = () => {
                        initAndScan();
                        video.removeEventListener('playing', onPlaying);
                    };
                    video.addEventListener('loadedmetadata', onLoaded, { once: true });
                    video.addEventListener('playing', onPlaying, { once: true });
                    video.play().catch(() => {
                        // Fallback attempt
                        setTimeout(() => initAndScan(), 400);
                    });

                    // Final fallback in case events are missed
                    setTimeout(() => { if (!initAndScan()) initAndScan(); }, 800);
                })
                .catch(err => {
                    console.error('Camera access error:', err);
                    alert('Tidak dapat mengakses kamera. Pastikan izin kamera diaktifkan.');
                    @this.stopScanning();
                });
            };

            window['stopScanner' + scannerId] = function() {
                if (video && video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                    try { video.pause(); } catch(_) {}
                    video.srcObject = null;
                }
                
                if (canvasContext && canvas) {
                    canvasContext.clearRect(0, 0, canvas.width, canvas.height);
                }
                
                if (animationFrameId) {
                    cancelAnimationFrame(animationFrameId);
                    animationFrameId = null;
                }
                
                const indicator = document.getElementById('scan-indicator-' + scannerId);
                if (indicator) {
                    indicator.classList.add('hidden');
                }
                lastScannedCode = null;
                lastScanTime = 0;
                isInCooldown = false;
            };

            function scanFrame() {
                if (!video || !canvas || !canvasContext) return;
                
                // Check Livewire component state safely
                try {
                    if (!@this.isScanning) return;
                } catch (e) {
                    // Component might be destroyed, stop scanning
                    return;
                }

                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvasContext.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    const imageData = canvasContext.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: 'dontInvert',
                    });

                    if (code && code.data && !isInCooldown) {
                        const currentTime = Date.now();
                        
                        // Prevent duplicate scans within 2 seconds
                        if (code.data !== lastScannedCode || currentTime - lastScanTime > 2000) {
                            lastScannedCode = code.data;
                            lastScanTime = currentTime;
                            isInCooldown = true;
                            
                            // Visual feedback
                            playBeep(scannerId);
                            flashScreen(scannerId, 'green');
                            showCooldownMessage(scannerId, cooldownMs);
                            
                            // Send to Livewire component
                            @this.handleScan(code.data).then(() => {
                                // Stop scanner after scan if stopAfterScan is enabled
                                if (@json($stopAfterScan)) {
                                    window['stopScanner' + scannerId]();
                                } else {
                                    // Release cooldown after specified duration
                                    setTimeout(() => {
                                        isInCooldown = false;
                                        hideCooldownMessage(scannerId);
                                    }, cooldownMs);
                                }
                            });
                        }
                    }
                }

                // Continue scanning if still active
                try {
                    if (@this.isScanning) {
                        animationFrameId = requestAnimationFrame(scanFrame);
                    }
                } catch (e) {
                    // Component destroyed, stop animation
                    if (animationFrameId) {
                        cancelAnimationFrame(animationFrameId);
                        animationFrameId = null;
                    }
                }
            }

            window['handleManualInput' + scannerId] = function(event) {
                const input = event.target;
                const barcode = input.value.trim();
                
                if (barcode && !isInCooldown) {
                    isInCooldown = true;
                    playBeep(scannerId);
                    flashScreen(scannerId, 'green');
                    showCooldownMessage(scannerId, cooldownMs);
                    
                    @this.handleScan(barcode).then(() => {
                        // Stop scanner after manual input if stopAfterScan is enabled
                        if (@json($stopAfterScan)) {
                            window['stopScanner' + scannerId]();
                        } else {
                            // Release cooldown after specified duration
                            setTimeout(() => {
                                isInCooldown = false;
                                hideCooldownMessage(scannerId);
                            }, cooldownMs);
                        }
                    });
                    input.value = '';
                }
            };

            function playBeep(id) {
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.1);
                } catch (e) {
                    console.warn('Audio playback not supported', e);
                }
            }

            function flashScreen(id, color) {
                const indicator = document.getElementById('scan-indicator-' + id);
                if (indicator) {
                    indicator.style.backgroundColor = color === 'green' ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.3)';
                    
                    setTimeout(() => {
                        indicator.style.backgroundColor = 'transparent';
                    }, 200);
                }
            }

            function showCooldownMessage(id, duration) {
                const videoElement = document.querySelector(`#scanner-video-${id}`);
                if (!videoElement || !videoElement.parentElement) return;
                
                const statusOverlay = videoElement.parentElement.querySelector('.absolute.bottom-0');
                if (statusOverlay) {
                    const countdown = Math.ceil(duration / 1000);
                    statusOverlay.innerHTML = `
                        <p class="text-white text-sm text-center">
                            <span class="inline-block w-2 h-2 bg-yellow-500 rounded-full mr-2"></span>
                            {{ __('Label was scanned within the last 5 minutes. Avoid repeated scans to prevent duplicate data.') }}
                        </p>
                    `;
                    statusOverlay.classList.add('bg-gradient-to-t', 'from-black/80', 'p-3');
                    statusOverlay.classList.remove('hidden');
                }
            }

            function hideCooldownMessage(id) {
                const videoElement = document.querySelector(`#scanner-video-${id}`);
                if (!videoElement || !videoElement.parentElement) return;
                
                const statusOverlay = videoElement.parentElement.querySelector('.absolute.bottom-0');
                if (!statusOverlay) return;
                
                // Safely check isScanning state
                try {
                    if (@this.isScanning) {
                        statusOverlay.innerHTML = `
                            <p class="text-white text-sm text-center">
                                <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></span>
                                {{ __('Point the camera at the barcode/QR code') }}
                            </p>
                        `;
                    }
                } catch (e) {
                    // Component might be destroyed, skip update
                }
            }

            // Auto-start if enabled
            @if($autoStart)
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(() => window['startScanner' + scannerId](), 500);
                });
            @endif

            // Cleanup on Livewire navigation (wire:navigate)
            document.addEventListener('livewire:navigating', () => {
                window['stopScanner' + scannerId]();
            });

            // Cleanup on page unload
            window.addEventListener('beforeunload', () => {
                window['stopScanner' + scannerId]();
            });

            // Cleanup when component is removed from DOM
            document.addEventListener('livewire:navigated', () => {
                // Stop scanner if this component is no longer in the DOM
                if (!document.getElementById('scanner-video-' + scannerId)) {
                    window['stopScanner' + scannerId]();
                }
            });

            // Cleanup when page visibility changes (user switches tabs)
            document.addEventListener('visibilitychange', () => {
                try {
                    if (document.hidden && @this.isScanning) {
                        window['stopScanner' + scannerId]();
                        @this.stopScanning();
                    }
                } catch (e) {
                    // Component might be destroyed, just stop scanner
                    window['stopScanner' + scannerId]();
                }
            });

            // Livewire component cleanup
            Livewire.on('scanner-cleanup-' + scannerId, () => {
                window['stopScanner' + scannerId]();
            });
        })();
    </script>
</div>
