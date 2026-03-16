@props([
    'show' => false,
    'pendingPccSlipNo' => '',
    'pendingPccAlias' => '',
    'pendingPccPartName' => '',
    'scannedCcpCode' => '',
    'ccpItems' => [],
    'title' => 'Konfirmasi Pemeriksaan CCP',
    'subtitle' => 'Cross-check berhasil. Verifikasi CCP di bawah sebelum submit.',
])

@if($show)
<div class="fixed inset-0 z-50 bg-black/90 backdrop-blur-sm">
    <div class="flex flex-col h-full">
        {{-- Header --}}
        <div class="p-4 md:p-6 bg-base-100/95 border-b">
            <h2 class="text-xl md:text-2xl font-bold text-base-content">{{ $title }}</h2>
            <p class="text-sm opacity-70">{{ $subtitle }}</p>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-auto p-4 md:p-6 space-y-6">
            {{-- PCC Data --}}
            <x-card title="Data Label PCC" shadow>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs opacity-70">Slip No</label>
                        <p class="font-semibold">{{ $pendingPccSlipNo }}</p>
                    </div>
                    <div>
                        <label class="text-xs opacity-70">Alias (Part No)</label>
                        <p class="font-semibold">{{ $pendingPccAlias }}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs opacity-70">Part Name</label>
                        <p class="font-semibold">{{ $pendingPccPartName }}</p>
                    </div>
                </div>
            </x-card>

            {{-- Cross-Check Result --}}
            <x-card title="Hasil Cross-Check" class="border-success" shadow>
                <div class="flex items-center gap-3">
                    <x-icon name="o-check-circle" class="w-8 h-8 text-success" />
                    <div>
                        <p class="text-sm opacity-70">Nilai yang Discan (Real-Life)</p>
                        <p class="font-semibold text-lg">{{ $scannedCcpCode }}</p>
                    </div>
                </div>
                <p class="text-xs text-success mt-2">✓ Cocok dengan Alias PCC</p>
            </x-card>

            {{-- CCP Images and Description --}}
            @if (count($ccpItems) > 0)
                <x-card title="Critical Control Point (CCP) - Periksa Sebelum Konfirmasi" shadow>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($ccpItems as $ccp)
                            <div class="space-y-3">
                                @if($ccp['img'])
                                    <div class="aspect-square bg-base-200 rounded-lg overflow-hidden">
                                        <img src="{{ $ccp['img'] }}" alt="CCP Image" class="w-full h-full object-contain" />
                                    </div>
                                @else
                                    <div class="aspect-square bg-base-200 rounded-lg flex items-center justify-center">
                                        <x-icon name="o-photo" class="w-16 h-16 opacity-30" />
                                    </div>
                                @endif
                                <div>
                                    <div class="text-xs opacity-70">Revision: {{ $ccp['revision'] }}</div>
                                    <p class="text-sm mt-1">{{ $ccp['description'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @else
                <x-card title="Critical Control Point (CCP)" class="border-warning" shadow>
                    <div class="flex items-center gap-3 p-6 text-center justify-center">
                        <x-icon name="o-exclamation-triangle" class="w-12 h-12 text-warning" />
                        <div>
                            <h3 class="font-bold text-lg">Tidak Ada CCP Aktif</h3>
                            <p class="text-sm opacity-70 mt-1">Tidak ada critical control point yang dikonfigurasi untuk part ini pada tahap saat ini</p>
                        </div>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Actions --}}
        <div class="p-4 bg-base-100/95 border-t flex gap-3 justify-end">
            <x-button label="Batal" class="btn-ghost" icon="o-x-mark" wire:click="cancelScan" />
            <x-button label="Konfirmasi - Pemeriksaan Selesai" class="btn-success btn-lg" icon="o-check" wire:click="confirmScan" />
        </div>
    </div>
</div>
@endif
