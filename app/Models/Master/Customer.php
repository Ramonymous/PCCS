<?php

namespace App\Models\Master;

use App\Models\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasUlid; // Apply the reusable ULID trait

    protected $table = 'customers';

    protected $fillable = [
        'id',
        'code',
        'name',
        'address',
        'email',
        'phone',
        'is_active',
    ];

    /**
     * Explicitly cast attributes to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the finished goods associated with the customer.
     * Renamed from 'finishgood' to 'finishGoods' for standard Eloquent pluralization.
     */
    public function finishGoods(): HasMany
    {
        // Joins: customers.id = finish_goods.customer_id
        return $this->hasMany(FinishGood::class, 'customer_id', 'id');
    }
}
