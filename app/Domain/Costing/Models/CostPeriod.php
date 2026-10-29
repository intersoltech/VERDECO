<?php

namespace App\Domain\Costing\Models;

use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

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
        'reopened_by',
        'reopened_at',
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
        'reopened_at' => 'datetime',
    ];

    private bool $allowLifecycleTransition = false;

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Cost periods with lifecycle history cannot be deleted.');
        });

        static::updating(function (CostPeriod $period): void {
            if ($period->isDirty('status') && ! $period->allowLifecycleTransition) {
                throw new LogicException('Cost period status must be changed through its lifecycle methods.');
            }

            if (in_array($period->getOriginal('status'), ['approved', 'closed'], true)
                && $period->isDirty(['tenant_id', 'plant_id', 'period_start', 'period_end', 'actual_cost', 'standard_cost', 'variance_cost', 'output_kg', 'comments'])) {
                throw new LogicException('Approved or closed cost period values are immutable until the period is reopened.');
            }
        });
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

    public function events(): HasMany
    {
        return $this->hasMany(CostPeriodEvent::class, 'cost_period_id')->orderBy('id');
    }

    public function approve(?User $user = null): void
    {
        if ($user === null) {
            throw new LogicException('An authenticated user is required to approve a cost period.');
        }

        if ($this->status !== 'open') {
            throw new LogicException('Only open cost periods can be approved.');
        }

        $this->status = 'approved';
        $this->approved_by = $user?->id;
        $this->approved_at = now();
        $this->saveLifecycleTransition('approved', $user);
    }

    public function close(?User $user = null): void
    {
        if ($user === null) {
            throw new LogicException('An authenticated user is required to close a cost period.');
        }

        if ($this->status !== 'approved') {
            throw new LogicException('Only approved cost periods can be closed.');
        }

        if ($this->approved_by === $user->id) {
            throw new LogicException('The approver cannot close the same cost period.');
        }

        $this->status = 'closed';
        $this->closed_by = $user?->id;
        $this->closed_at = now();
        $this->saveLifecycleTransition('closed', $user);
    }

    public function reopen(?User $user = null): void
    {
        if ($user === null) {
            throw new LogicException('An authenticated user is required to reopen a cost period.');
        }

        if ($this->status !== 'closed') {
            throw new LogicException('Only closed cost periods can be reopened.');
        }

        $this->status = 'open';
        $this->closed_by = null;
        $this->closed_at = null;
        $this->reopened_by = $user?->id;
        $this->reopened_at = now();
        $this->saveLifecycleTransition('reopened', $user);
    }

    private function saveLifecycleTransition(string $event, User $actor): void
    {
        $fromStatus = (string) $this->getOriginal('status');
        $toStatus = (string) $this->status;

        DB::transaction(function () use ($event, $actor, $fromStatus, $toStatus): void {
            $this->allowLifecycleTransition = true;

            try {
                $this->save();
                CostPeriodEvent::query()->create([
                    'tenant_id' => $this->tenant_id,
                    'plant_id' => $this->plant_id,
                    'cost_period_id' => $this->getKey(),
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'actor_email' => $actor->email,
                    'event' => $event,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'occurred_at' => now(),
                ]);
            } finally {
                $this->allowLifecycleTransition = false;
            }
        });
    }
}
