{{-- Audio Feedback Script for Scanner Events --}}
@pushOnce('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('scan-feedback', (event) => {
            const type = event.type || event[0]?.type;
            
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                if (type === 'success') {
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.1);
                } else if (type === 'error') {
                    oscillator.frequency.value = 400;
                    oscillator.type = 'square';
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.3);
                } else if (type === 'warning') {
                    oscillator.frequency.value = 600;
                    oscillator.type = 'triangle';
                    gainNode.gain.setValueAtTime(0.2, audioContext.currentTime);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.2);
                }
            } catch (e) {
                console.warn('Audio feedback not supported', e);
            }
        });
    });
</script>
@endPushOnce
