<?php

namespace App\Models\Master;

use App\Models\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Customer\HPM\Pcc;
use App\Models\Customer\HPM\CCP;
use App\Models\Master\Customer;

class FinishGood extends Model
{
    use HasUlid;

    protected $table = 'finish_goods';

    protected $fillable = [
        'customer_id',
        'part_number',
        'part_name',
        'alias',
        'model',
        'variant',
        'stock',
        'wh_address',
        'type', // 'ASSY' or 'DIRECT'
        'is_active',
    ];

    protected $casts = [
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke Customer pemilik Finish Good.
     * finish_goods.customer_id = customers.id
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relasi ke model Pcc.
     * finish_goods.part_number = pccs.part_no
     */
    public function pccs(): HasMany
    {
        return $this->hasMany(Pcc::class, 'part_no', 'part_number');
    }

    public function ccps(): HasMany
    {
        return $this->hasMany(CCP::class, 'finish_good_id', 'id');
    }
}
