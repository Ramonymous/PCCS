<?php

namespace App\Models\Customer\HPM;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUlid;

class PccEvent extends Model
{
    use HasUlid;

    protected $table = 'hpm_pcc_events';

    protected $fillable = [
        'pcc_trace_id',
        'event_users',
        'event_type', //'PRODUCTION CHECK', 'RECEIVED', 'PDI CHECK', 'DELIVERY'
        'event_timestamp',
        'remarks',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime:Y-m-d H:i:s',
    ];

    // relasi ke PccTrace (belongs to current trace)
    public function trace()
    {
        return $this->belongsTo(PccTrace::class, 'pcc_trace_id', 'id');
    }

    //relasi ke model User (event_users)
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'event_users', 'id');
    }
}
