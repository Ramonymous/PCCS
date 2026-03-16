<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasUlid
{
    /**
     * Initialize the trait by setting key properties.
     * This method is automatically called by Eloquent.
     *
     * @return void
     */
    public function initializeHasUlid(): void
    {
        // Disable auto-incrementing and set key type to string for ULIDs
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /**
     * Automatically generate a ULID for the primary key on creation.
     *
     * @return void
     */
    protected static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            // Only generate a ULID if the key is not already set
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::ulid();
            }
        });
    }
}
