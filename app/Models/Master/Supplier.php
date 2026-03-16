<?php

namespace App\Models\Master;

use App\Models\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUlid; // Replaces $incrementing, $keyType, and the boot() method

    protected $table = 'suppliers';

    protected $fillable = [
        'id',
        'code',
        'name',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    /**
     * Explicitly cast attributes to native types.
     * Casting 'is_active' to boolean is a common best practice.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // The manual boot() method has been moved to the HasUlid trait.
}
