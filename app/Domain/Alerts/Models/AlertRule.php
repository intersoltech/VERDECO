<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'code',
        'name',
        'description',
        'severity',
        'enabled',
        'threshold_value',
        'comparison_operator',
        'cooldown_minutes',
        'message_template',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'threshold_value' => 'decimal:2',
        'cooldown_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            if (empty($rule->tenant_id) && !empty($rule->plant_id)) {
                $rule->tenant_id = $rule->plant?->tenant_id;
            }

            if (!empty($rule->plant_id) && empty($rule->tenant_id)) {
                $rule->tenant_id = Plant::query()->find($rule->plant_id)?->tenant_id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
