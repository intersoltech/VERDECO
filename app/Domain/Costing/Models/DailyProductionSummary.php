<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyProductionSummary extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'production_date',
        'status',
        'batch_total_cost',
        'daily_expense_total',
        'incident_total_cost',
        'actual_total_cost',
        'output_kg',
        'cost_per_kg',
        'calculated_at',
    ];

    protected $casts = [
        'production_date' => 'date',
        'batch_total_cost' => 'decimal:2',
        'daily_expense_total' => 'decimal:2',
        'incident_total_cost' => 'decimal:2',
        'actual_total_cost' => 'decimal:2',
        'output_kg' => 'decimal:4',
        'cost_per_kg' => 'decimal:6',
        'calculated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyProductionSummary $summary): void {
            $summary->tenant_id = Plant::query()->whereKey($summary->plant_id)->value('tenant_id');
        });
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(\App\Domain\Costing\Models\DailyExpense::class, 'daily_production_summary_id');
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(ProductionBatch::class, 'daily_production_batch')
            ->withPivot(['tenant_id', 'plant_id'])
            ->withTimestamps();
    }
}