<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CostPeriodEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'cost_period_id',
        'actor_id',
        'actor_name',
        'actor_email',
        'event',
        'from_status',
        'to_status',
        'occurred_at',
        'context',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'context' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Cost period events are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Cost period events are immutable.');
        });
    }

    public function costPeriod(): BelongsTo
    {
        return $this->belongsTo(CostPeriod::class, 'cost_period_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
