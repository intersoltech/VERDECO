<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Organization\Models\Plant;
use App\Domain\Quality\Models\BatchQualityResult;
use App\Models\User;
use Database\Factories\ProductionBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductionBatch extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): ProductionBatchFactory
    {
        return ProductionBatchFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'product_grade_id',
        'batch_number',
        'batch_date',
        'status',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'adjustment_reason',
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
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'calculated_at' => 'datetime',
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
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'batch_number',
                'good_output_kg',
                'total_cost',
                'cost_per_kg',
                'closed_by',
                'closed_at',
                'reopened_by',
                'reopened_at',
                'adjustment_reason',
            ])
            ->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionBatch $batch): void {
            $batch->tenant_id = Plant::query()->whereKey($batch->plant_id)->value('tenant_id');
        });

        static::updating(function (ProductionBatch $batch): void {
            if ($batch->getOriginal('status') === 'closed'
                && $batch->status === 'closed'
                && $batch->isDirty(['input_kg', 'good_output_kg', 'material_cost', 'labor_cost', 'utility_cost', 'overhead_cost', 'total_cost', 'net_cost', 'cost_per_kg'])) {
                throw new LogicException('Closed production batch is immutable. Modifications require an authorized reopening and adjustment entry.');
            }
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

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionBatchMaterial::class, 'production_batch_id');
    }

    public function qualityResults(): HasMany
    {
        return $this->hasMany(BatchQualityResult::class, 'production_batch_id');
    }

    public function close(User $user): void
    {
        $this->update([
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => now(),
        ]);
    }

    public function reopen(User $user, string $reason): void
    {
        $this->update([
            'status' => 'running',
            'reopened_by' => $user->id,
            'reopened_at' => now(),
            'adjustment_reason' => $reason,
        ]);
    }
}
