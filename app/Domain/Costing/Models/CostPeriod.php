<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostPeriod extends Model
{
    protected $fillable = [
        'tenant_id',
        'plant_id',
        'period_start',
        'period_end',
        'status',
        'actual_cost',
        'standard_cost',
        'variance_cost',
        'output_kg',
        'comments',
        'approved_by',
        'approved_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'actual_cost' => 'decimal:2',
        'standard_cost' => 'decimal:2',
        'variance_cost' => 'decimal:2',
        'output_kg' => 'decimal:4',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approve(?User $user = null): void
    {
        $this->status = 'approved';
        $this->approved_by = $user?->id ?? $this->approved_by;
        $this->approved_at = $this->approved_at ?? now();
        $this->save();
    }

    public function close(?User $user = null): void
    {
        $this->status = 'closed';
        $this->closed_by = $user?->id ?? $this->closed_by;
        $this->closed_at = $this->closed_at ?? now();
        $this->save();
    }

    public function reopen(?User $user = null): void
    {
        $this->status = 'open';
        $this->closed_by = null;
        $this->closed_at = null;
        $this->save();
    }
}
