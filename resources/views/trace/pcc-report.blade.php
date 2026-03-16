<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PccReportExport;

new class extends Component {
    use Toast;
    // Always using a date range now (start of month -> end of month by default)
    public string $date = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 15;
    public string $search = '';

    public function mount(): void
    {
        $start = now()->startOfMonth()->format('Y-m-d');
        $end   = now()->endOfMonth()->format('Y-m-d');
        $this->dateFrom = $start;
        $this->dateTo   = $end;
        // keep a single date mirror (middle of range) if needed elsewhere
        $this->date = now()->format('Y-m-d');
    }

    public function apply(): void
    {
        // Validation and normalization for the always-on range
        if ($this->dateFrom === '' || $this->dateTo === '') {
            $this->warning('Lengkapi tanggal FROM dan TO.', null, 'toast-top');
            return;
        }
        if ($this->dateFrom > $this->dateTo) {
            [$this->dateFrom, $this->dateTo] = [$this->dateTo, $this->dateFrom];
        }

        // Notify reloaded
        $this->dispatch('$refresh');
    }

    public function export()
    {
    $from = $this->dateFrom;
    $to   = $this->dateTo;

        if (!$from || !$to) {
            $this->warning('Pilih tanggal terlebih dahulu.');
            return;
        }

        $filename = 'pcc-report_' . $from . ($from !== $to ? ('_to_' . $to) : '') . '.xlsx';
        return Excel::download(new PccReportExport($from, $to), $filename);
    }

    public function resetFilters(): void
    {
        $today = now()->format('Y-m-d');
        $this->rangeMode = false;
        $this->date = $today;
        $this->dateFrom = $today;
        $this->dateTo = $today;
        $this->search = '';
        $this->perPage = 15;
        $this->dispatch('$refresh');
    }

    public function dataQuery()
    {
        // Optimized single query using correlated subselects instead of multiple leftJoin + groupBy chains.
        // This avoids joining the traces/events table multiple times and leverages indexes on (pcc_id, event_type, event_timestamp).

    $from = $this->dateFrom;
    $to   = $this->dateTo;
        if (!$from) { $from = now()->format('Y-m-d'); }
        if (!$to)   { $to = $from; }

        $search = trim($this->search);

        // Effective date expression used for filtering
        $effectiveDateExpr = DB::raw('COALESCE(s.adjusted_date, s.schedule_date, pccs.date)');

        $base = \App\Models\Customer\HPM\Pcc::query()
            ->from('pccs') // ensure table name explicit for whereColumn usage in subqueries
            ->leftJoin('hpm_schedules as s', 's.slip_number', '=', 'pccs.slip_no')
            ->leftJoin('finish_goods as fg', 'fg.part_number', '=', 'pccs.part_no')
            ->whereBetween($effectiveDateExpr, [$from, $to])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('pccs.slip_barcode', 'like', "%$search%")
                        ->orWhere('pccs.slip_no', 'like', "%$search%")
                        ->orWhere('fg.part_number', 'like', "%$search%")
                        ->orWhere('fg.part_name', 'like', "%$search%")
                        ->orWhere('pccs.part_no', 'like', "%$search%")
                        ->orWhere('pccs.part_name', 'like', "%$search%");
                });
            });

        // Helper closure to build latest event timestamp or user name per type
        $latestEventTs = function (string $type) {
            return DB::table('hpm_pcc_events as e')
                ->select('e.event_timestamp')
                ->join('hpm_pcc_traces as t', 't.id', '=', 'e.pcc_trace_id')
                ->whereColumn('t.pcc_id', 'pccs.id')
                ->where('e.event_type', $type)
                ->orderByDesc('e.event_timestamp')
                ->limit(1);
        };

        $latestEventUser = function (string $type) {
            return DB::table('hpm_pcc_events as e')
                ->select('u.name')
                ->join('hpm_pcc_traces as t', 't.id', '=', 'e.pcc_trace_id')
                ->leftJoin('users as u', 'u.id', '=', 'e.event_users')
                ->whereColumn('t.pcc_id', 'pccs.id')
                ->where('e.event_type', $type)
                ->orderByDesc('e.event_timestamp')
                ->limit(1);
        };

        return $base
            ->select([
                'pccs.id', // kept for internal grouping by pagination
                'pccs.slip_barcode as barcode',
                DB::raw('COALESCE(fg.part_number, pccs.part_no) as fg_part_number'),
                DB::raw('COALESCE(fg.part_name, pccs.part_name) as fg_part_name'),
            ])
            ->selectSub($latestEventTs('PRODUCTION CHECK'), 'production_checked_at')
            ->selectSub($latestEventUser('PRODUCTION CHECK'), 'production_by')
            ->selectSub($latestEventTs('RECEIVED'), 'received_at')
            ->selectSub($latestEventUser('RECEIVED'), 'received_by')
            ->selectSub($latestEventTs('PDI CHECK'), 'pdi_checked_at')
            ->selectSub($latestEventUser('PDI CHECK'), 'pdi_by')
            ->selectSub($latestEventTs('DELIVERY'), 'delivery_at')
            ->selectSub($latestEventUser('DELIVERY'), 'delivery_by')
            ->orderBy('pccs.slip_barcode');
    }

    public function with(): array
    {
        $headers = [
            ['key' => 'barcode', 'label' => 'Slip Barcode'],
            ['key' => 'fg_part_number', 'label' => 'Part Number'],
            ['key' => 'fg_part_name', 'label' => 'Part Name'],
            ['key' => 'production_checked_at', 'label' => 'Production'],
            ['key' => 'production_by', 'label' => 'By'],
            ['key' => 'received_at', 'label' => 'Received'],
            ['key' => 'received_by', 'label' => 'By'],
            ['key' => 'pdi_checked_at', 'label' => 'PDI'],
            ['key' => 'pdi_by', 'label' => 'By'],
            ['key' => 'delivery_at', 'label' => 'Delivery'],
            ['key' => 'delivery_by', 'label' => 'By'],
        ];

        $rows = $this->dataQuery()->paginate($this->perPage);

        $rows->getCollection()->transform(function ($row) {
            foreach (['production_checked_at','received_at','pdi_checked_at','delivery_at'] as $col) {
                if ($row->$col) {
                    $row->$col = \Carbon\Carbon::parse($row->$col)->format('Y-m-d H:i');
                }
            }
            return $row;
        });

        return [
            'headers' => $headers,
            'rows' => $rows,
            'date' => $this->date, // retained for potential single-date references
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'perPage' => $this->perPage,
            'total' => $rows->total(),
        ];
    }
}; ?>

<div>
    <x-header title="PCC Report" subtitle="Slip barcode events by date" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-down-tray" class="btn-primary" label="Download" wire:click="export" spinner="export" />
        </x-slot:actions>
    </x-header>

    <x-card shadow title="Filters" subtitle="Filter by date range, search, and page size" separator>
        <div class="flex flex-col gap-3">
            <div class="flex flex-col md:flex-row md:items-end gap-3">
                <x-input type="date" label="From" icon="o-calendar" wire:model.defer="dateFrom" class="min-w-48" />
                <x-input type="date" label="To" icon="o-calendar" wire:model.defer="dateTo" class="min-w-48" />

                <x-input label="Search" placeholder="Barcode, slip, part no/name" icon="o-magnifying-glass" wire:model.live.debounce.400ms="search" class="flex-1 min-w-64" />

                <div class="flex gap-2 md:ml-auto">
                    <x-button class="btn-primary" icon="o-funnel" label="Apply" wire:click="apply" spinner="apply" />
                    <x-button class="btn-ghost" icon="o-arrow-path" label="Reset" wire:click="resetFilters" spinner="resetFilters" />
                </div>
            </div>
        </div>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @php $from = $dateFrom; $to = $dateTo; @endphp
                <x-badge :value="($from && $to) ? ($from === $to ? $from : ($from.' → '.$to)) : '-'" class="badge-ghost" />
                <x-badge :value="($rows?->total() ?? 0).' results'" class="badge-outline" />
            </div>
        </x-slot:actions>
    </x-card>

    <x-card shadow class="mt-6 overflow-x-auto">
        <x-table
            :headers="$headers"
            :rows="$rows"
            with-pagination
            per-page="perPage"
            :per-page-values="[10, 15, 25, 50]"
            class="table-zebra table-sm"
        >
            @scope('cell_production_checked_at', $row)
                {{ $row->production_checked_at ?? '-' }}
            @endscope
            @scope('cell_production_by', $row)
                @if($row->production_by)
                    <x-badge :value="$row->production_by" class="badge-ghost badge-sm" />
                @else
                    -
                @endif
            @endscope
            @scope('cell_received_at', $row)
                {{ $row->received_at ?? '-' }}
            @endscope
            @scope('cell_received_by', $row)
                @if($row->received_by)
                    <x-badge :value="$row->received_by" class="badge-ghost badge-sm" />
                @else
                    -
                @endif
            @endscope
            @scope('cell_pdi_checked_at', $row)
                {{ $row->pdi_checked_at ?? '-' }}
            @endscope
            @scope('cell_pdi_by', $row)
                @if($row->pdi_by)
                    <x-badge :value="$row->pdi_by" class="badge-ghost badge-sm" />
                @else
                    -
                @endif
            @endscope
            @scope('cell_delivery_at', $row)
                {{ $row->delivery_at ?? '-' }}
            @endscope
            @scope('cell_delivery_by', $row)
                @if($row->delivery_by)
                    <x-badge :value="$row->delivery_by" class="badge-ghost badge-sm" />
                @else
                    -
                @endif
            @endscope
            @scope('empty-state', $row)
                <div class="p-8 text-center">
                    <x-icon name="o-magnifying-glass" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                    <p class="font-semibold text-base-content/70">No data</p>
                    <p class="text-xs text-base-content/50 mt-1">Try adjusting date, range, or search filters.</p>
                </div>
            @endscope
            {{-- NOTE: Mary @scope directive implementation expects TWO arguments. The original single-argument usage triggered 'Undefined array key 1'. Passing a dummy second arg ($rows) resolves it. --}}
        </x-table>
    </x-card>
</div>
