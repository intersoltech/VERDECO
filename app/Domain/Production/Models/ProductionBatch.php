<?php

namespace App\Domain\Production\Models;

use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'product_grade_id',
        'batch_number',
        'batch_date',
        'status',
        'input_kg',
        'good_output_kg',
        'recovery_credit',
        'labor_regular_rate',
        'labor_overtime_rate',
        'labor_regular_hours',
        'labor_overtime_hours',
        'labor_overtime_multiplier',
        'utility_kwh',
        'utility_rate',
        'overhead_pool',
        'overhead_basis',
        'incident_cost',
        'incident_allocation_rule',
        'material_cost',
        'labor_cost',
        'utility_cost',
        'overhead_cost',
        'total_cost',
        'net_cost',
        'yield_percent',
        'cost_per_kg',
        'calculated_at',
    ];

    protected $casts = [
        'batch_date' => 'date',
        'input_kg' => 'decimal:4',
        'good_output_kg' => 'decimal:4',
        'recovery_credit' => 'decimal:2',
        'labor_regular_rate' => 'decimal:6',
        'labor_overtime_rate' => 'decimal:6',
        'labor_regular_hours' => 'decimal:4',
        'labor_overtime_hours' => 'decimal:4',
        'labor_overtime_multiplier' => 'decimal:4',
        'utility_kwh' => 'decimal:4',
        'utility_rate' => 'decimal:6',
        'overhead_pool' => 'decimal:2',
        'incident_cost' => 'decimal:2',
        'material_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'utility_cost' => 'decimal:2',
        'overhead_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'net_cost' => 'decimal:2',
        'yield_percent' => 'decimal:4',
        'cost_per_kg' => 'decimal:6',
        'calculated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductionBatch $batch): void {
            $batch->tenant_id = Plant::query()->whereKey($batch->plant_id)->value('tenant_id');
        });
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'line_id');
    }

    public function productGrade(): BelongsTo
    {
        return $this->belongsTo(ProductGrade::class, 'product_grade_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(\App\Domain\Production\Models\ProductionBatchMaterial::class, 'production_batch_id');
    }
}