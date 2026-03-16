<?php

namespace App\Models\Customer\HPM;

use App\Models\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasUlid;

    protected $table = 'hpm_schedules';

    protected $fillable = [
        'slip_number',
        'schedule_date',
        'adjusted_date',
        'schedule_time',
        'adjusted_time',
        'delivery_quantity',
        'adjustment_quantity',
    ];

    public $timestamps = true;

    protected $casts = [
        'schedule_date' => 'date:Y-m-d',
        'adjusted_date' => 'date:Y-m-d',
        'schedule_time' => 'string',
        'adjusted_time' => 'string',
        'delivery_time' => 'string',
        'delivery_quantity' => 'integer',
        'adjustment_quantity' => 'integer',
        'slip_number' => 'string',
    ];

    /**
     * Relasi ke model Pcc.
     * hpm_schedules.slip_number = pccs.slip_no
     */
    public function pccs(): HasMany
    {
        return $this->hasMany(Pcc::class, 'slip_no', 'slip_number');
    }

    /**
     * Accessor for related PCCs count.
     *
     * Prefer the eager-loaded withCount value when present to avoid N+1 queries;
     * otherwise, fall back to counting the relationship on demand.
     */
    public function getPccsCountAttribute($value): int
    {
        if ($value !== null) {
            return (int) $value;
        }

        return (int) $this->pccs()->count();
    }
}