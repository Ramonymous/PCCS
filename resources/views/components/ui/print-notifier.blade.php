<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Notifications\PrintJobComplete;

new class extends Component {

    // PERBAIKAN: Hanya satu state untuk mengontrol modal
    public bool $showModal = false;

    // State untuk mengontrol *konten* di dalam modal
    public bool $isProcessing = false;
    public ?string $downloadUrl = null;
    public ?string $statusMessage = null;

    /**
     * Cek status saat komponen pertama kali dimuat.
     * Berguna jika user me-refresh halaman saat job sedang berjalan.
     */
    public function mount()
    {
        $this->checkStatus(false); // Jangan tandai sudah dibaca saat mount
    }

    /**
     * Dipicu oleh 'index.blade.php' untuk memulai proses.
     */
    #[On('print-job-started')]
    public function startProcessing()
    {
        // PERBAIKAN: Hanya hapus notifikasi 'PrintJobComplete' yang lama
        auth()->user()
            ->unreadNotifications()
            ->where('type', PrintJobComplete::class)
            ->delete();
        
        // Atur state untuk menampilkan modal "processing"
        $this->isProcessing = true;
        $this->downloadUrl = null;
        $this->statusMessage = null;
        $this->showModal = true; // Tampilkan modal
    }

    /**
     * Cek notifikasi terbaru dari database.
     * Dipanggil oleh wire:poll.
     */
    public function checkStatus($markAsRead = true)
    {
        $notification = auth()->user()
            ->unreadNotifications()
            ->where('type', PrintJobComplete::class)
            ->latest()
            ->first();

        if ($notification) {
            // Notifikasi ditemukan, job selesai (sukses atau gagal)
            
            // PERBAIKAN: Ubah state internal modal, tapi JANGAN tutup modal
            $this->isProcessing = false; // Ganti konten ke "completed"
            $this->downloadUrl = $notification->data['download_url'] ?? null;
            $this->statusMessage = $notification->data['message'] ?? 'Status tidak diketahui.';
            $this->showModal = true; // Pastikan modal tetap terbuka

            if ($markAsRead) {
                $notification->markAsRead();
            }
        }
        
        // Jika tidak ada notifikasi, tidak terjadi apa-apa.
        // $isProcessing akan tetap true dan polling berlanjut.
    }

    /**
     * Menutup modal (untuk semua kasus).
     */
    public function closeModal()
    {
        $this->showModal = false;
        // Reset state untuk persiapan job berikutnya
        $this->isProcessing = false;
        $this->downloadUrl = null;
        $this->statusMessage = null;
    }
}; ?>

<div>
    {{-- 
      PERBAIKAN: 
      Menggunakan SATU modal 'showModal'.
      Konten di dalamnya berubah berdasarkan state 'isProcessing'.
      Ini mencegah "jeda" atau "kedipan" saat modal berganti.
    --}}
    <x-modal wire:model="showModal" 
             :title="$isProcessing ? 'Membuat Dokumen...' : 'Cetak Selesai!'" 
             class="!p-0" 
             persistent 
             separator>

        @if ($isProcessing)
            {{-- 1. Tampilan "Processing" --}}
            {{-- Polling ditempatkan di sini, sehingga berhenti saat $isProcessing = false --}}
            <div class="p-6 flex flex-col items-center justify-center space-y-4" wire:poll.2000ms="checkStatus">
                <svg class="animate-spin h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div class="text-lg font-semibold text-base-content">
                    {{ __('Creating labels...') }}
                </div>
                <div class="text-sm text-base-content/70">
                    {{ __('Please wait, the process may take a few moments.') }}
                </div>
                <div class="text-xs text-info mt-2">
                    {{ __('Status will be updated automatically.') }}
                </div>
            </div>
        @else
            {{-- 2. Tampilan "Completed" (Sukses atau Gagal) --}}
            <div class="p-6 flex flex-col items-center justify-center space-y-4">
                @if ($downloadUrl)
                    {{-- Kasus Sukses --}}
                    <x-icon name="o-check-circle" class="w-12 h-12 text-success" />
                    <div class="text-lg font-semibold text-base-content">
                        {{ __('Your File is Ready to Download.') }}
                    </div>
                    <div class="text-sm text-base-content/70 text-center">
                        {{ $statusMessage ?: __('Click the button below to download the document.') }}
                    </div>
                @else
                    {{-- Kasus Gagal --}}
                    <x-icon name="o-exclamation-triangle" class="w-12 h-12 text-error" />
                    <div class="text-lg font-semibold text-error">
                        {{ __('An Error Occurred!') }}
                    </div>
                    <div class="text-sm text-base-content/70 text-center">
                        {{ $statusMessage ?: __('File creation failed due to internal issues.') }}
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            {{-- Tombol hanya tampil saat proses selesai --}}
            @if (!$isProcessing)
                @if ($downloadUrl)
                    {{-- Tombol Unduh (hanya jika sukses) --}}
                    <a href="{{ $downloadUrl }}" target="_blank" class="btn btn-primary" @click="$wire.closeModal()">
                        <x-icon name="o-arrow-down-tray" class="w-5 h-5" />
                        Unduh File
                    </a>
                @endif
                
                {{-- Tombol Tutup (untuk sukses/gagal) --}}
                <x-button :label="__('Close')" wire:click="closeModal" class="btn-ghost" />
            @endif
        </x-slot:actions>
    </x-modal>
</div>
