@props([
    'recentScans' => [],
    'title' => 'Recent Scans',
])

<x-card :title="$title . ' (' . count($recentScans) . ')'" shadow>
    <div class="space-y-2 max-h-[700px] overflow-y-auto">
        @forelse($recentScans as $scan)
            <div class="p-3 bg-base-200 rounded-lg hover:bg-base-300 transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="font-semibold text-sm">{{ $scan['slip_no'] }}</div>
                        <div class="text-xs text-gray-600 mt-1">
                            {{ $scan['part_no'] }} - {{ $scan['part_name'] }}
                        </div>
                        @if(isset($scan['remarks']) && $scan['remarks'])
                            <div class="text-xs text-gray-500 mt-1">{{ $scan['remarks'] }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-500">{{ $scan['timestamp'] }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">
                <x-icon name="o-qr-code" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                <p>Belum ada scan</p>
            </div>
        @endforelse
    </div>
</x-card>
