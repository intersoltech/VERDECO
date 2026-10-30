<?php

namespace App\Domain\Incidents\Models;

use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostIncident extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\CostIncidentFactory
    {
        return \Database\Factories\CostIncidentFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'plant_id',
        'line_id',
        'incident_type',
        'severity',
        'root_cause_category',
        'description',
        'owner',
        'status',
        'occurred_at',
        'cash_cost',
        'opportunity_cost',
        'allocation_rule',
        'approved_by',
        'approved_at',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Production\Models\ProductionLine::class, 'line_id');
    }

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

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CostIncidentLine::class, 'incident_id');
    }

    public function approve(?User $user = null): void
    {
        $this->status = 'approved';
        $this->approved_by = $user?->id ?? $this->approved_by;
        $this->approved_at = $this->approved_at ?? now();
        $this->closed_by = null;
        $this->closed_at = null;
        $this->reopened_by = null;
        $this->reopened_at = null;
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
        $this->reopened_by = $user?->id ?? $this->reopened_by;
        $this->reopened_at = $this->reopened_at ?? now();
        $this->closed_by = null;
        $this->closed_at = null;
        $this->save();
    }
}
