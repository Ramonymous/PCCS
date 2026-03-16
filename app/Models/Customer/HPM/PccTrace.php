<?php

namespace App\Models\Customer\HPM;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUlid;

class PccTrace extends Model
{
    use HasUlid;

    protected $table = 'hpm_pcc_traces';

    protected $fillable = [
        'pcc_id',
        'event_type', //'PRODUCTION CHECK', 'RECEIVED', 'PDI CHECK', 'DELIVERY'
        'event_timestamp',
        'remarks',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime:Y-m-d H:i:s',
    ];

    //relasi ke model Pcc
    public function pcc()
    {
        return $this->belongsTo(Pcc::class, 'pcc_id', 'id');
    }

    // relasi ke PccEvent (one-to-many: one trace has many events/logs)
    public function events()
    {
        return $this->hasMany(PccEvent::class, 'pcc_trace_id', 'id');
    }
}
