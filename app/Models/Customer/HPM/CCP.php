<?php

namespace App\Models\Customer\HPM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasUlid;

/**
 * CCP (Critical Control Point) Model
 * 
 * Represents quality check points for finish goods at different production stages.
 * Each CCP can be assigned to a specific stage or to ALL stages.
 * 
 * Stages:
 * - PRODUCTION CHECK: Welding/production verification
 * - PDI CHECK: Quality assurance verification
 * - DELIVERY: Final delivery verification
 * - ALL: Applies to all stages
 */
class CCP extends Model
{
    use HasFactory, HasUlid;
    
    protected $table = 'pcc_cpps';

    protected $fillable = [
        'finish_good_id',
        'stage',
        'check_point_img',
        'revision',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Valid stages
    public const STAGE_PRODUCTION_CHECK = 'PRODUCTION CHECK';
    public const STAGE_PDI_CHECK = 'PDI CHECK';
    public const STAGE_DELIVERY = 'DELIVERY';
    public const STAGE_ALL = 'ALL';

    /**
     * Scope to filter CCPs by stage
     * CCPs with stage='ALL' are included for all stages
     */
    public function scopeForStage($query, string $stage)
    {
        return $query->where(function ($q) use ($stage) {
            $q->where('stage', $stage)
              ->orWhere('stage', self::STAGE_ALL);
        });
    }

    /**
     * Relationship to FinishGood model
     */
    public function finishGood()
    {
        return $this->belongsTo(\App\Models\Master\FinishGood::class, 'finish_good_id', 'id');
    }
}
