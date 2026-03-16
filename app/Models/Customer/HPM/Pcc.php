<?php

namespace App\Models\Customer\HPM;

use App\Models\Traits\HasUlid;
use App\Models\Master\FinishGood;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\Customer\HPM\PccEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pcc extends Model
{
    use HasUlid;

    protected $table = 'pccs';

    protected $fillable = [
        'from',
        'to',
        'supply_address',
        'next_supply_address',
        'ms_id',
        'inventory_category',
        'part_no',
        'part_name',
        'color_code',
        'ps_code',
        'order_class',
        'prod_seq_no',
        'kd_lot_no',
        'ship',
        'slip_no',
        'slip_barcode',
        'printed',
        'date',
        'time',
        'hns',
    ];

    protected $casts = [
        'ship' => 'integer',
        'printed' => 'boolean',
        'date' => 'date:Y-m-d',
        'time' => 'string',
    ];
    
    protected $appends = ['effective_date', 'effective_time'];

    /** 
     * Relasi ke model FinishGood via alias.
     * pccs.part_no = finish_goods.alias
     */
    public function finishGood(): HasOne
    {
        return $this->hasOne(FinishGood::class, 'alias', 'part_no');
    }

    /** Relasi ke model Schedule */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'slip_no', 'slip_number');
    }

    // relasi ke model PccEvent & PccTrace
    public function trace()
    {
        return $this->hasMany(PccTrace::class, 'pcc_id', 'id');
    }

    /**
     * All events associated with this PCC (via PccTrace).
     */
    public function events(): HasManyThrough
    {
        return $this->hasManyThrough(
            PccEvent::class, // final related model
            PccTrace::class, // through/intermediate model
            'pcc_id',        // Foreign key on PccTrace referencing Pcc
            'pcc_trace_id',  // Foreign key on PccEvent referencing PccTrace
            'id',            // Local key on Pcc
            'id'             // Local key on PccTrace used by PccEvent
        );
    }

    /** Effective Date — ambil dari schedule kalau ada, kalau tidak pakai date dari PCC */
    public function getEffectiveDateAttribute()
    {
        if ($this->relationLoaded('schedule') && $this->schedule) {
            // Prioritaskan adjusted_date kalau ada, lalu schedule_date
            $date = $this->schedule->adjusted_date ?? $this->schedule->schedule_date;
            if ($date) {
                return $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
            }
        }

        // Fallback to PCC's own date
        if ($this->date) {
            return $this->date instanceof \Carbon\Carbon ? $this->date->format('Y-m-d') : $this->date;
        }

        return null;
    }

    /** Effective Time — ambil dari schedule kalau ada, kalau tidak pakai time dari PCC */
    public function getEffectiveTimeAttribute()
    {
        if ($this->relationLoaded('schedule') && $this->schedule) {
            // Prioritaskan adjusted_time kalau ada, lalu schedule_time
            $time = $this->schedule->adjusted_time ?? $this->schedule->schedule_time;
            if ($time) {
                // Schedule times are cast as datetime, extract time portion
                if ($time instanceof \Carbon\Carbon) {
                    return $time->format('H:i:s');
                }
                // If string, ensure it's in H:i:s format
                if (is_string($time)) {
                    // Could be '08:00:00' or '1970-01-01 08:00:00'
                    if (strlen($time) > 8) {
                        return \Carbon\Carbon::parse($time)->format('H:i:s');
                    }
                    return $time;
                }
            }
        }

        // Fallback to PCC's own time
        if ($this->time) {
            if ($this->time instanceof \Carbon\Carbon) {
                return $this->time->format('H:i:s');
            }
            return $this->time;
        }

        return null;
    }

    /**
     * Count of PCCs sharing the same slip number (grouped by slip_no).
     *
     * Prefer using the related Schedule's withCount('pccs') if available to avoid extra queries;
     * otherwise, fall back to counting by slip_no directly.
     */
    public function getSlipGroupCountAttribute(): int
    {
        if ($this->relationLoaded('schedule') && $this->schedule) {
            $count = data_get($this->schedule, 'pccs_count');
            if ($count !== null) {
                return (int) $count;
            }

            return (int) $this->schedule->pccs()->count();
        }

        return (int) static::where('slip_no', $this->slip_no)->count();
    }
}