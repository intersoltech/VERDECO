<?php

declare(strict_types=1);

namespace App\Domain\Costing\Models;

use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionLine;
use App\Models\User;
use Database\Factories\StandardCostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StandardCost extends Model
{
    use HasFactory, LogsActivity;

    protected static function newFactory(): StandardCostFactory
    {
        return StandardCostFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'product_grade_id',
        'status', // draft, pending_approval, approved, rejected
        'effective_from',
        'effective_to',
        'standard_yield_percent',
        'kg_per_hour',
        'kwh_per_kg',
        'labor_hours_per_ton',
        'material_usage_kg_per_kg',
        'overhead_rate',
        'created_by',
        'submitted_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'approved_at' => 'datetime',
        'standard_yield_percent' => 'decimal:4',
        'kg_per_hour' => 'decimal:4',
        'kwh_per_kg' => 'decimal:6',
        'labor_hours_per_ton' => 'decimal:6',
        'material_usage_kg_per_kg' => 'decimal:6',
        'overhead_rate' => 'decimal:6',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'standard_yield_percent',
                'kg_per_hour',
                'kwh_per_kg',
                'labor_hours_per_ton',
                'material_usage_kg_per_kg',
                'overhead_rate',
                'approved_by',
                'approved_at',
            ])
            ->logOnlyDirty();
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function submitForApproval(User $user): void
    {
        $this->update([
            'status' => 'pending_approval',
            'submitted_by' => $user->id,
            'rejection_reason' => null,
        ]);
    }

    public function approve(User $user): void
    {
        if ($this->created_by && $this->created_by === $user->id) {
            throw new LogicException('Dual control violation: Maker cannot approve their own standard cost revision.');
        }

        if ($this->submitted_by && $this->submitted_by === $user->id) {
            throw new LogicException('Dual control violation: Maker cannot approve their own standard cost revision.');
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function reject(User $user, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
